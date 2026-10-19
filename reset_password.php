<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/password_otp.php';

date_default_timezone_set('Asia/Bangkok');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['reset_password_csrf'])) {
    $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
}

function resetPasswordRecord(
    PDO $pdo,
    string $token,
    bool $forUpdate = false
): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $sql = "
        SELECT
            pr.id,
            pr.user_id,
            pr.expires_at,
            u.password
        FROM password_resets pr
        INNER JOIN users u
            ON u.id = pr.user_id
        WHERE pr.token_hash = ?
          AND pr.used_at IS NULL
          AND pr.expires_at > NOW()
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([hash('sha256', $token)]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record ?: null;
}

$token = strtolower(trim(
    (string)($_POST['token'] ?? $_GET['token'] ?? '')
));
$error = '';
$success = '';
$resetRecord = resetPasswordRecord($pdo, $token);
$tokenValid = $resetRecord !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (
        $csrfToken === ''
        || !hash_equals(
            (string)$_SESSION['reset_password_csrf'],
            $csrfToken
        )
    ) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif (!$tokenValid) {
        $error = 'ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้อง หมดอายุ หรือถูกใช้งานแล้ว';
    } elseif ($newPassword === '' || $confirmPassword === '') {
        $error = 'กรุณากรอกรหัสผ่านใหม่ให้ครบทั้งสองช่อง';
    } elseif (strlen($newPassword) < 8) {
        $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (password_verify($newPassword, (string)$resetRecord['password'])) {
        $error = 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม';
    } else {
        try {
            $pdo->beginTransaction();
            $lockedRecord = resetPasswordRecord($pdo, $token, true);

            if (!$lockedRecord) {
                $pdo->rollBack();
                $tokenValid = false;
                $error = 'ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้อง หมดอายุ หรือถูกใช้งานแล้ว';
            } elseif (password_verify($newPassword, (string)$lockedRecord['password'])) {
                $pdo->rollBack();
                $error = 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม';
            } else {
                $passwordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                if ($passwordHash === false) {
                    throw new RuntimeException('ไม่สามารถเข้ารหัสรหัสผ่านใหม่ได้');
                }

                $passwordStmt = $pdo->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                ");
                $passwordStmt->execute([
                    $passwordHash,
                    (int)$lockedRecord['user_id']
                ]);

                $tokenStmt = $pdo->prepare("
                    UPDATE password_resets
                    SET used_at = NOW()
                    WHERE user_id = ?
                      AND used_at IS NULL
                ");
                $tokenStmt->execute([(int)$lockedRecord['user_id']]);

                invalidatePasswordOtpForUser(
                    $pdo,
                    (int)$lockedRecord['user_id']
                );

                $pdo->commit();
                unset($_SESSION['reset_password_csrf']);
                $tokenValid = false;
                $success = 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว กรุณาเข้าสู่ระบบ';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('RESET PASSWORD ERROR: ' . $e->getMessage());
            $error = 'ไม่สามารถตั้งรหัสผ่านใหม่ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

$pageTitle = 'ตั้งรหัสผ่านใหม่';
$pageCss = 'login.css';

include 'includes/header.php';

?>

<section class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-card-header">
                <div class="login-icon">
                    <i class="fa-solid fa-lock-open"></i>
                </div>
                <span class="login-label">BIZCOMP CLUB</span>
                <h1>ตั้งรหัสผ่านใหม่</h1>
                <p>กำหนดรหัสผ่านใหม่สำหรับบัญชี Bizcomp Club ของคุณ</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="login-alert success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <a href="login.php" class="login-submit" style="text-decoration:none;box-sizing:border-box;">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    กลับไปหน้าเข้าสู่ระบบ
                </a>
            <?php elseif (!$tokenValid): ?>
                <div class="login-alert error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>
                        <?= htmlspecialchars(
                            $error !== ''
                                ? $error
                                : 'ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้อง หมดอายุ หรือถูกใช้งานแล้ว',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                </div>

                <a href="forgot_password.php" class="login-submit" style="text-decoration:none;box-sizing:border-box;">
                    <i class="fa-solid fa-rotate-right"></i>
                    ขอลิงก์ตั้งรหัสผ่านใหม่
                </a>
            <?php else: ?>
                <?php if ($error !== ''): ?>
                    <div class="login-alert error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form action="reset_password.php" method="POST" class="login-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['reset_password_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <div class="login-form-group">
                        <label for="new_password">รหัสผ่านใหม่</label>
                        <div class="login-input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                placeholder="อย่างน้อย 8 ตัวอักษร"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                    </div>

                    <div class="login-form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                        <div class="login-input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="กรอกรหัสผ่านใหม่อีกครั้ง"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="login-submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        บันทึกรหัสผ่านใหม่
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
