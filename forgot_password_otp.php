<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/password_otp.php';
require_once __DIR__ . '/includes/otp_mail.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$email = (string)($_SESSION['otp_email'] ?? '');
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!otpCsrfValid()) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
    } else {
        // A new request starts a new reset flow; do not carry a prior
        // success message or reset capability into this flow.
        unset(
            $_SESSION['otp_reset_capability'],
            $_SESSION['otp_reset_expires_at'],
            $_SESSION['otp_password_reset_success'],
            $_SESSION['otp_password_reset_success_at']
        );
        try {
            if (!isBizcompMailConfigured()) {
                throw new RuntimeException('OTP mail configuration is missing.');
            }
            $service = otpService($pdo);
            $mailTransport = new PhpMailerOtpMailTransport();
            $result = $service->request($email, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $mailTransport);
            $sameFlow = ($_SESSION['otp_email'] ?? '') === $email;
            $status = $result['status'];
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_requested_at'] = time();
            $_SESSION['otp_expires_at'] = $status === 'rate_limited'
                ? ($sameFlow ? (int)($_SESSION['otp_expires_at'] ?? time()) : time())
                : (int)($result['expires_at'] ?? time());
            $_SESSION['otp_resend_at'] = (int)($result['resend_at'] ?? (time() + 60));
            $_SESSION['otp_notice'] = $status === 'rate_limited'
                ? 'ขอรหัสบ่อยเกินไป กรุณารอเวลาที่แสดงก่อนขอใหม่ หากมีรหัสที่ยังไม่หมดอายุสามารถใช้ยืนยันได้'
                : ($status === 'cooldown'
                    ? 'กรุณาใช้รหัสจากคำขอก่อนหน้า หรือรอเวลาที่แสดงก่อนขอใหม่'
                    : PasswordOtpService::GENERIC_RESPONSE);
            header('Location: verify_otp.php', true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('BC1 OTP request failed: ' . get_class($e) . ' (code ' . $e->getCode() . '). Check OTP secret, tables and mail configuration.');
            $error = 'ระบบส่ง OTP ไม่พร้อมใช้งานชั่วคราว กรุณาติดต่อผู้ดูแลระบบหรือลองใหม่ภายหลัง';
        }
    }
}

$pageTitle = 'ลืมรหัสผ่านด้วย OTP';
$pageCss = 'password_otp.css';
include __DIR__ . '/includes/header.php';
?>
<main class="otp-page"><section class="otp-card">
    <div class="otp-icon"><i class="fa-solid fa-key"></i></div>
    <span class="otp-kicker">BIZCOMP CLUB · OTP</span>
    <h1>ลืมรหัสผ่าน</h1>
    <p>กรอกอีเมลสมาชิกเพื่อขอรหัส OTP สำหรับตั้งรหัสผ่านใหม่</p>
    <?php if ($message !== ''): ?><div class="otp-alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="otp-alert otp-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="forgot_password_otp.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(otpCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <label for="email">อีเมล</label>
        <input class="otp-text" id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
        <button class="otp-primary" type="submit">ขอรหัส OTP</button>
    </form>
    <?php if (!empty($_SESSION['otp_requested_at'])): ?>
        <a class="otp-link" href="verify_otp.php">ไปยังหน้ากรอกรหัส OTP</a>
    <?php endif; ?>
    <p class="otp-security-note">รหัส OTP มีอายุ 5 นาทีและใช้ได้เพียงครั้งเดียว</p>
    <a class="otp-back" href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
</section></main>
<?php include __DIR__ . '/includes/footer.php'; ?>
