<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/register_flow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_SESSION['user_id'])) {
    header('Location: register.php');
    exit;
}

if (!validRegistrationCsrf((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['register_error'] = 'คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่อีกครั้ง';
    header('Location: register.php');
    exit;
}

[$data, $yearLevel, $validationError] = validateRegistrationDetails($_POST);
$_SESSION['register_old'] = $data;

if ($validationError !== null) {
    $_SESSION['register_error'] = $validationError;
    header('Location: register.php');
    exit;
}

$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
$existingStep = $_SESSION['register_step1'] ?? [];
$canKeepPassword =
    is_array($existingStep) &&
    validPreparedRegistration($existingStep) &&
    $password === '' &&
    $confirmPassword === '' &&
    !empty($existingStep['password_hash']) &&
    hash_equals((string)($existingStep['email'] ?? ''), $data['email']);

if (!$canKeepPassword) {
    if (strlen($password) < 8) {
        $_SESSION['register_error'] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        header('Location: register.php');
        exit;
    }

    if ($password !== $confirmPassword) {
        $_SESSION['register_error'] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        header('Location: register.php');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        $_SESSION['register_error'] = 'ไม่สามารถเตรียมข้อมูลบัญชีได้ กรุณาลองใหม่อีกครั้ง';
        header('Location: register.php');
        exit;
    }
} else {
    $passwordHash = (string)$existingStep['password_hash'];
}

try {
    $conflict = findRegistrationConflict($pdo, $data['email'], $data['student_id']);
} catch (Throwable $e) {
    $_SESSION['register_error'] = 'ไม่สามารถตรวจสอบข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
    header('Location: register.php');
    exit;
}
if ($conflict !== null) {
    $_SESSION['register_error'] = $conflict['message'];
    $_SESSION['register_email_exists'] = in_array($conflict['type'], ['email', 'pending'], true);
    header('Location: register.php');
    exit;
}

$_SESSION['register_step1'] = $data + [
    'year_level' => $yearLevel,
    'password_hash' => $passwordHash,
    'submission_token' => bin2hex(random_bytes(32)),
    'prepared_at' => time(),
];

unset(
    $_SESSION['register_old'],
    $_SESSION['register_error'],
    $_SESSION['register_email_exists'],
    $_SESSION['register_payment_old'],
    $_SESSION['register_payment_error']
);

header('Location: register_payment.php');
exit;
