<?php
require_once __DIR__ . '/includes/bootstrap.php';

// New password-reset requests use OTP. Existing unexpired links continue to
// be handled by reset_password.php during the compatibility window.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: forgot_password_otp.php', true, 303);
exit;
