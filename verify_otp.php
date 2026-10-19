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

if (empty($_SESSION['otp_requested_at']) || !filter_var((string)($_SESSION['otp_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot_password_otp.php', true, 303);
    exit;
}
$notice = (string)($_SESSION['otp_notice'] ?? '');
unset($_SESSION['otp_notice']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!otpCsrfValid()) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif (!filter_var((string)($_SESSION['otp_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณาเริ่มคำขอ OTP ใหม่';
    } else {
        try {
            $result = otpService($pdo)->verify(
                (string)$_SESSION['otp_email'],
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_POST['otp'] ?? '')
            );
            if (($result['status'] ?? '') === 'verified') {
                session_regenerate_id(true);
                // Successful verification grants reset permission, not a
                // completed reset; clear any success state from older flows.
                unset($_SESSION['otp_password_reset_success'], $_SESSION['otp_password_reset_success_at']);
                $_SESSION['otp_reset_capability'] = $result['capability'];
                $_SESSION['otp_reset_expires_at'] = time() + 600;
                header('Location: reset_password_otp.php', true, 303);
                exit;
            }
            $error = match ($result['status'] ?? '') {
                'expired' => 'รหัสหมดอายุแล้ว กรุณาขอรหัสใหม่',
                'locked' => 'กรอกรหัสผิดครบ 5 ครั้งแล้ว กรุณาขอรหัสใหม่',
                'rate_limited' => 'ตรวจสอบรหัสบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่',
                default => 'รหัสไม่ถูกต้องหรือหมดอายุ กรุณาตรวจสอบแล้วลองใหม่',
            };
        } catch (Throwable $e) {
            error_log('BC1 OTP verification failed: ' . get_class($e) . ' (code ' . $e->getCode() . ').');
            $error = 'ระบบไม่พร้อมใช้งานชั่วคราว กรุณาลองใหม่';
        }
    }
}

$pageTitle = 'ยืนยัน OTP';
$pageCss = 'password_otp.css';
include __DIR__ . '/includes/header.php';
?>
<main class="otp-page"><section class="otp-card">
    <div class="otp-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <span class="otp-kicker">BIZCOMP CLUB · OTP</span>
    <h1>ยืนยันรหัส OTP</h1>
    <p>กรอกรหัสตัวเลข 6 หลัก รหัสมีอายุ 5 นาที</p>
    <?php if ($notice !== ''): ?><div class="otp-alert" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="otp-alert otp-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="otp-clock">หมดอายุใน <strong id="otp-countdown">05:00</strong></div>
    <form method="post" action="verify_otp.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(otpCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <label for="otp">รหัส OTP</label>
        <input class="otp-code" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
        <button class="otp-primary" type="submit">ตรวจสอบรหัส</button>
    </form>
    <form method="post" action="forgot_password_otp.php" class="otp-resend-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(otpCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars((string)($_SESSION['otp_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <button class="otp-link" id="otp-resend" type="submit" disabled>ขอ OTP ใหม่</button>
    </form>
    <p class="otp-security-note">เพื่อความปลอดภัย ระบบจะจำกัดจำนวนครั้งที่กรอกรหัสไม่ถูกต้อง</p>
    <a class="otp-back" href="forgot_password_otp.php">กลับ</a>
</section></main>
<script>
(() => {
  const expiresAt = Number(<?= json_encode((int)($_SESSION['otp_expires_at'] ?? time())) ?>) * 1000;
  const resendAt = Number(<?= json_encode((int)($_SESSION['otp_resend_at'] ?? time())) ?>) * 1000;
  const clock = document.getElementById('otp-countdown');
  const resend = document.getElementById('otp-resend');
  const tick = () => {
    const left = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
    clock.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
    const wait = Math.max(0, Math.ceil((resendAt - Date.now()) / 1000));
    resend.disabled = wait > 0;
    resend.textContent = wait ? `ขอ OTP ใหม่ได้ใน ${wait} วินาที` : 'ขอ OTP ใหม่';
  };
  tick(); window.setInterval(tick, 1000);
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
