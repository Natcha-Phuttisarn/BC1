<?php
require_once __DIR__ . '/bootstrap.php';

interface PasswordOtpMailTransport
{
    public function send(string $email, string $name, string $code): bool;
}

final class PdoPasswordOtpRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new InvalidArgumentException('OTP repository requires MySQL or MariaDB.');
        }
    }

    private function beginAtomic(): void
    {
        $this->pdo->beginTransaction();
    }

    private function commitAtomic(): void
    {
        $this->pdo->commit();
    }

    private function rollbackAtomic(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (Throwable $ignored) {
        }
    }

    private function lockSuffix(): string
    {
        return ' FOR UPDATE';
    }

    /** @return array{status:string, name?:string, email?:string} */
    public function issue(string $email, string $emailHash, string $ipHash, string $codeHmac, int $now): array
    {
        $this->beginAtomic();
        try {
            $buckets = $this->lockBuckets($emailHash, $ipHash, $now);
            $emailBucket = $buckets['email'];
            $ipBucket = $buckets['ip'];

            if ($emailBucket['last_request_at'] !== null && $now - (int)$emailBucket['last_request_at'] < 60) {
                $this->commitAtomic();
                return ['status' => 'cooldown', 'resend_at' => (int)$emailBucket['last_request_at'] + 60,
                    'expires_at' => (int)$emailBucket['last_request_at'] + 300];
            }
            if ((int)$emailBucket['request_count'] >= 5 || (int)$ipBucket['request_count'] >= 20) {
                $this->commitAtomic();
                return ['status' => 'rate_limited', 'resend_at' => max(
                    (int)$emailBucket['request_count'] >= 5 ? (int)$emailBucket['window_started_at'] + 3600 : 0,
                    (int)$ipBucket['request_count'] >= 20 ? (int)$ipBucket['window_started_at'] + 3600 : 0
                )];
            }

            $memberSql = 'SELECT id, first_name, last_name, email, role, account_status FROM users WHERE email = ? LIMIT 1' . $this->lockSuffix();
            $memberStmt = $this->pdo->prepare($memberSql);
            $memberStmt->execute([$email]);
            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

            $this->incrementBucket('email', $emailHash, 'request_count', $now);
            $this->incrementBucket('ip', $ipHash, 'request_count', $now);

            if (!$member || $member['role'] !== 'user' || $member['account_status'] !== 'active') {
                $this->commitAtomic();
                return ['status' => 'generic'];
            }

            $expiresAt = $now + 300;
            $sql = 'INSERT INTO password_reset_otp_challenges
                (email_hash,user_id,code_hmac,expires_at,attempts,verified_at,reset_token_hash,reset_expires_at,reset_used_at,updated_at)
                VALUES (?,?,?,?,0,NULL,NULL,NULL,NULL,?)
                ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),code_hmac=VALUES(code_hmac),expires_at=VALUES(expires_at),
                attempts=0,verified_at=NULL,reset_token_hash=NULL,reset_expires_at=NULL,reset_used_at=NULL,updated_at=VALUES(updated_at)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$emailHash, (int)$member['id'], $codeHmac, $expiresAt, $now]);
            $this->commitAtomic();
            return [
                'status' => 'issued',
                'name' => trim($member['first_name'] . ' ' . $member['last_name']),
                'email' => $member['email'],
            ];
        } catch (Throwable $e) {
            $this->rollbackAtomic();
            throw $e;
        }
    }

    /** @return array{status:string, capability?:string} */
    public function verify(string $emailHash, string $ipHash, string $candidateHmac, string $capabilityHash, int $now): array
    {
        $this->beginAtomic();
        try {
            $buckets = $this->lockBuckets($emailHash, $ipHash, $now);
            if ((int)$buckets['email']['attempt_count'] >= 30 || (int)$buckets['ip']['attempt_count'] >= 100) {
                $this->commitAtomic();
                return ['status' => 'rate_limited'];
            }
            $this->incrementBucket('email', $emailHash, 'attempt_count', $now);
            $this->incrementBucket('ip', $ipHash, 'attempt_count', $now);

            $stmt = $this->pdo->prepare('SELECT * FROM password_reset_otp_challenges WHERE email_hash = ?' . $this->lockSuffix());
            $stmt->execute([$emailHash]);
            $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$challenge || empty($challenge['code_hmac']) || (int)$challenge['attempts'] >= 5) {
                $this->commitAtomic();
                return ['status' => 'invalid'];
            }
            if ((int)$challenge['expires_at'] <= $now) {
                $this->pdo->prepare('UPDATE password_reset_otp_challenges SET code_hmac = NULL WHERE email_hash = ?')->execute([$emailHash]);
                $this->commitAtomic();
                return ['status' => 'expired'];
            }
            if (!hash_equals((string)$challenge['code_hmac'], $candidateHmac)) {
                $attempts = (int)$challenge['attempts'] + 1;
                $stmt = $this->pdo->prepare('UPDATE password_reset_otp_challenges SET attempts = ?, code_hmac = ? WHERE email_hash = ?');
                $stmt->execute([$attempts, $attempts >= 5 ? null : $challenge['code_hmac'], $emailHash]);
                $this->commitAtomic();
                return ['status' => $attempts >= 5 ? 'locked' : 'invalid'];
            }

            $stmt = $this->pdo->prepare('UPDATE password_reset_otp_challenges
                SET code_hmac=NULL,verified_at=?,reset_token_hash=?,reset_expires_at=?,reset_used_at=NULL
                WHERE email_hash=? AND code_hmac=? AND attempts < 5 AND expires_at > ?');
            $stmt->execute([$now, $capabilityHash, $now + 600, $emailHash, $challenge['code_hmac'], $now]);
            if ($stmt->rowCount() !== 1) {
                $this->rollbackAtomic();
                return ['status' => 'invalid'];
            }
            $this->commitAtomic();
            return ['status' => 'verified'];
        } catch (Throwable $e) {
            $this->rollbackAtomic();
            throw $e;
        }
    }

    public function invalidate(string $emailHash, string $codeHmac): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_reset_otp_challenges SET code_hmac=NULL WHERE email_hash=? AND code_hmac=?');
        $stmt->execute([$emailHash, $codeHmac]);
    }

    /** @return array{status:string} */
    public function reset(string $capabilityHash, string $newPasswordHash, string $newPassword, int $now): array
    {
        $this->beginAtomic();
        try {
            $lookup = $this->pdo->prepare('SELECT user_id FROM password_reset_otp_challenges WHERE reset_token_hash=?');
            $lookup->execute([$capabilityHash]);
            $userId = $lookup->fetchColumn();
            if (!$userId) {
                $this->commitAtomic();
                return ['status' => 'invalid'];
            }

            $userSql = 'SELECT id,password,role,account_status FROM users WHERE id=? LIMIT 1' . $this->lockSuffix();
            $userStmt = $this->pdo->prepare($userSql);
            $userStmt->execute([(int)$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            $challengeSql = 'SELECT * FROM password_reset_otp_challenges WHERE reset_token_hash=?' . $this->lockSuffix();
            $challengeStmt = $this->pdo->prepare($challengeSql);
            $challengeStmt->execute([$capabilityHash]);
            $challenge = $challengeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$challenge || (int)$challenge['user_id'] !== (int)$userId
                || $challenge['reset_used_at'] !== null || (int)$challenge['reset_expires_at'] <= $now
                || $user['role'] !== 'user' || $user['account_status'] !== 'active') {
                $this->commitAtomic();
                return ['status' => 'invalid'];
            }
            if (password_verify($newPassword, (string)$user['password'])) {
                $this->commitAtomic();
                return ['status' => 'same_password'];
            }

            $update = $this->pdo->prepare("UPDATE users SET password=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND role='user' AND account_status='active'");
            $update->execute([$newPasswordHash, (int)$userId]);
            if ($update->rowCount() !== 1) {
                $this->rollbackAtomic();
                return ['status' => 'invalid'];
            }
            $consume = $this->pdo->prepare('UPDATE password_reset_otp_challenges
                SET reset_used_at=?,reset_token_hash=NULL,reset_expires_at=NULL WHERE email_hash=? AND reset_token_hash=? AND reset_used_at IS NULL');
            $consume->execute([$now, $challenge['email_hash'], $capabilityHash]);
            if ($consume->rowCount() !== 1) {
                $this->rollbackAtomic();
                return ['status' => 'invalid'];
            }
            $this->pdo->prepare('UPDATE password_resets SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL')
                ->execute([(int)$userId]);
            $this->commitAtomic();
            return ['status' => 'reset'];
        } catch (Throwable $e) {
            $this->rollbackAtomic();
            throw $e;
        }
    }

    private function lockBuckets(string $emailHash, string $ipHash, int $now): array
    {
        $entries = [
            ['email', $emailHash],
            ['ip', $ipHash],
        ];
        usort($entries, static function (array $a, array $b): int {
            return strcmp($a[0] . $a[1], $b[0] . $b[1]);
        });
        foreach ($entries as [$scope, $hash]) {
            $insert = $this->pdo->prepare('INSERT IGNORE INTO password_reset_otp_limits (scope,key_hash,window_started_at) VALUES (?,?,?)');
            $insert->execute([$scope, $hash, $now]);
        }
        $buckets = [];
        foreach ($entries as [$scope, $hash]) {
            $stmt = $this->pdo->prepare('SELECT * FROM password_reset_otp_limits WHERE scope=? AND key_hash=?' . $this->lockSuffix());
            $stmt->execute([$scope, $hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Rate-limit bucket could not be locked.');
            }
            if ($now - (int)$row['window_started_at'] >= 3600) {
                $this->pdo->prepare('UPDATE password_reset_otp_limits SET window_started_at=?,request_count=0,attempt_count=0,last_request_at=NULL WHERE scope=? AND key_hash=?')
                    ->execute([$now, $scope, $hash]);
                $row['window_started_at'] = $now;
                $row['request_count'] = 0;
                $row['attempt_count'] = 0;
                $row['last_request_at'] = null;
            }
            $buckets[$scope] = $row;
        }
        return $buckets;
    }

    private function incrementBucket(string $scope, string $hash, string $column, int $now): void
    {
        if (!in_array($column, ['request_count', 'attempt_count'], true)) {
            throw new InvalidArgumentException('Invalid OTP counter.');
        }
        $lastRequest = $column === 'request_count' ? ',last_request_at=?' : '';
        $sql = "UPDATE password_reset_otp_limits SET {$column}={$column}+1{$lastRequest} WHERE scope=? AND key_hash=?";
        $params = $column === 'request_count' ? [$now, $scope, $hash] : [$scope, $hash];
        $this->pdo->prepare($sql)->execute($params);
    }
}

final class PasswordOtpService
{
    public const GENERIC_RESPONSE = 'หากอีเมลนี้เป็นบัญชีสมาชิกที่ใช้งานได้ ระบบจะดำเนินการส่งรหัสให้';
    private PdoPasswordOtpRepository $repository;
    private string $secret;

    public function __construct(PdoPasswordOtpRepository $repository, string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('OTP HMAC secret must be at least 32 bytes.');
        }
        $this->repository = $repository;
        $this->secret = $secret;
    }

    public function request(string $email, string $ip, PasswordOtpMailTransport $mail, ?int $now = null): array
    {
        $now = $now ?? time();
        $email = strtolower(trim($email));
        $emailHash = $this->emailHash($email);
        $ipHash = $this->ipHash($ip);
        $code = sprintf('%06d', random_int(0, 999999));
        $codeHmac = $this->codeHash($emailHash, $code);
        $result = $this->repository->issue($email, $emailHash, $ipHash, $codeHmac, $now);
        if ($result['status'] === 'issued') {
            if (!$mail->send((string)$result['email'], (string)$result['name'], $code)) {
                $this->repository->invalidate($emailHash, $codeHmac);
                return ['status' => 'mail_failed', 'message' => self::GENERIC_RESPONSE];
            }
        }
        return ['status' => $result['status'], 'message' => self::GENERIC_RESPONSE,
            'expires_at' => $result['expires_at'] ?? ($now + 300),
            'resend_at' => $result['resend_at'] ?? ($now + 60)];
    }

    public function verify(string $email, string $ip, string $code, ?int $now = null): array
    {
        $now = $now ?? time();
        $emailHash = $this->emailHash(strtolower(trim($email)));
        $ipHash = $this->ipHash($ip);
        $normalizedCode = preg_match('/^\d{6}$/', $code) ? $code : 'invalid';
        $capability = bin2hex(random_bytes(32));
        $result = $this->repository->verify(
            $emailHash,
            $ipHash,
            $this->codeHash($emailHash, $normalizedCode),
            hash('sha256', $capability),
            $now
        );
        if ($result['status'] === 'verified') {
            $result['capability'] = $capability;
        }
        return $result;
    }

    public function reset(string $capability, string $newPassword, ?int $now = null): array
    {
        if ($capability === '' || strlen($newPassword) < 8) {
            return ['status' => 'invalid'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            return ['status' => 'invalid'];
        }
        return $this->repository->reset(hash('sha256', $capability), $hash, $newPassword, $now ?? time());
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'email|' . strtolower(trim($email)), $this->secret);
    }

    public function ipHash(string $ip): string
    {
        return hash_hmac('sha256', 'ip|' . $ip, $this->secret);
    }

    private function codeHash(string $emailHash, string $code): string
    {
        return hash_hmac('sha256', 'otp|' . $emailHash . '|' . $code, $this->secret);
    }
}

function otpProductionSecret(): string
{
    $secret = trim(bcConfig('BC_OTP_HMAC_SECRET'));
    if (strlen($secret) < 64) {
        throw new RuntimeException('OTP service is not configured.');
    }
    return $secret;
}

function otpCsrfToken(): string
{
    if (empty($_SESSION['otp_csrf'])) {
        $_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['otp_csrf'];
}

function otpCsrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['otp_csrf'])
        && hash_equals((string)$_SESSION['otp_csrf'], (string)$_POST['csrf_token']);
}

function otpService(PDO $pdo): PasswordOtpService
{
    return new PasswordOtpService(new PdoPasswordOtpRepository($pdo), otpProductionSecret());
}

function invalidatePasswordOtpForUser(PDO $pdo, int $userId, ?int $now = null): void
{
    $timestamp = $now ?? time();
    $stmt = $pdo->prepare('UPDATE password_reset_otp_challenges
        SET code_hmac=NULL,reset_token_hash=NULL,reset_expires_at=NULL,
            reset_used_at=COALESCE(reset_used_at,?),updated_at=?
        WHERE user_id=? AND (code_hmac IS NOT NULL OR reset_token_hash IS NOT NULL)');
    $stmt->execute([$timestamp, $timestamp, $userId]);
}
