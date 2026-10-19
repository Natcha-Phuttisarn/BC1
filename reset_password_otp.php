<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/password_otp.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$error = '';
unset($_SESSION['otp_password_reset_success'], $_SESSION['otp_password_reset_success_at']);
$capability = (string)($_SESSION['otp_reset_capability'] ?? '');
$expiresAt = (int)($_SESSION['otp_reset_expires_at'] ?? 0);
$authorized = filter_var((string)($_SESSION['otp_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
    && $capability !== '' && $expiresAt > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!otpCsrfValid()) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif (!$authorized) {
        $error = 'ไม่พบสิทธิ์ตั้งรหัสผ่าน กรุณายืนยัน OTP ก่อน';
    } else {
        $password = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        } elseif ($password !== $confirm) {
            $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        } else {
            try {
                $result = otpService($pdo)->reset($capability, $password);
                if (($result['status'] ?? '') === 'reset') {
                    unset(
                        $_SESSION['otp_email'],
                        $_SESSION['otp_requested_at'],
                        $_SESSION['otp_expires_at'],
                        $_SESSION['otp_resend_at'],
                        $_SESSION['otp_reset_capability'],
                        $_SESSION['otp_reset_expires_at']
                    );
                    session_regenerate_id(true);
                    $_SESSION['login_success'] = 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่';
                    header('Location: login.php', true, 303);
                    exit;
                } else {
                    $error = ($result['status'] ?? '') === 'same_password'
                        ? 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม'
                        : 'สิทธิ์ตั้งรหัสผ่านหมดอายุหรือถูกใช้แล้ว กรุณายืนยัน OTP ใหม่';
                    if (($result['status'] ?? '') !== 'same_password') {
                        unset($_SESSION['otp_reset_capability'], $_SESSION['otp_reset_expires_at']);
                        $authorized = false;
                    }
                    unset($_SESSION['otp_password_reset_success'], $_SESSION['otp_password_reset_success_at']);
                }
            } catch (Throwable $e) {
                error_log('BC1 OTP reset failed: ' . get_class($e) . ' (code ' . $e->getCode() . ').');
                $error = 'ระบบไม่พร้อมใช้งานชั่วคราว กรุณาลองใหม่';
            }
        }
    }
}

$pageTitle = 'ตั้งรหัสผ่านใหม่ด้วย OTP';
$pageCss = 'password_otp.css';
include __DIR__ . '/includes/header.php';
?>
<main class="otp-page"><section class="otp-card">
    <div class="otp-icon"><i class="fa-solid fa-lock"></i></div>
    <span class="otp-kicker">BIZCOMP CLUB · OTP</span>
    <?php if (!$authorized): ?>
        <h1>ต้องยืนยัน OTP ก่อน</h1><p>ไม่สามารถเปิดหน้าตั้งรหัสผ่านโดยข้ามขั้นตอนยืนยันได้</p>
        <?php if ($error !== ''): ?><div class="otp-alert otp-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <a class="otp-primary otp-button-link" href="forgot_password_otp.php">เริ่มใหม่</a>
    <?php else: ?>
        <h1>ตั้งรหัสผ่านใหม่</h1><p>สิทธิ์นี้ใช้ได้ครั้งเดียวและหมดอายุภายใน 10 นาทีหลังยืนยัน OTP</p>
        <?php if ($error !== ''): ?><div class="otp-alert otp-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" action="reset_password_otp.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(otpCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <label for="new_password">รหัสผ่านใหม่</label><input class="otp-text" type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
            <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label><input class="otp-text" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            <button class="otp-primary" type="submit">บันทึกรหัสผ่านใหม่</button>
        </form>
    <?php endif; ?>
</section></main>
<?php include __DIR__ . '/includes/footer.php'; ?>
