<?php
require_once __DIR__ . '/includes/bootstrap.php';
// บันทึกการสมัครสมาชิกจากขั้นตอนชำระเงิน

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/register_flow.php';

if (isset($_SESSION['user_id'])) {
    header('Location: member_status.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register_payment.php');
    exit;
}

$step = $_SESSION['register_step1'] ?? [];
if (!is_array($step) || !validPreparedRegistration($step)) {
    unset($_SESSION['register_step1'], $_SESSION['register_payment_old']);
    $_SESSION['register_error'] =
        'ข้อมูลขั้นตอนที่ 1 ไม่มีอยู่หรือหมดอายุ กรุณากรอกข้อมูลใหม่อีกครั้ง';
    header('Location: register.php');
    exit;
}

if (!validRegistrationCsrf((string)($_POST['csrf_token'] ?? ''))) {
    registrationPaymentError('คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่อีกครั้ง');
}

$submittedToken = (string)($_POST['submission_token'] ?? '');
if (
    $submittedToken === '' ||
    !hash_equals((string)$step['submission_token'], $submittedToken)
) {
    registrationPaymentError('ข้อมูลยืนยันการสมัครไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
}

$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
$confirmed = isset($_POST['confirm_information']);
$privacyConsent = isset($_POST['privacy_consent']);

$_SESSION['register_payment_old'] = [
    'payment_method' => $paymentMethod,
    'confirm_information' => $confirmed,
    'privacy_consent' => $privacyConsent,
];

if (!in_array($paymentMethod, ['promptpay', 'bank_transfer'], true)) {
    registrationPaymentError('กรุณาเลือกช่องทางการชำระเงิน');
}

if (!$confirmed) {
    registrationPaymentError(
        'กรุณายืนยันว่าตรวจสอบข้อมูลและชำระค่าสมัครสมาชิกเรียบร้อยแล้ว'
    );
}

if (!$privacyConsent) {
    registrationPaymentError('กรุณายอมรับนโยบายความเป็นส่วนตัวก่อนสมัครสมาชิก');
}

[$validatedData, $yearLevel, $validationError] = validateRegistrationDetails($step);
if ($validationError !== null || $yearLevel !== (int)$step['year_level']) {
    unset($_SESSION['register_step1']);
    $_SESSION['register_old'] = $validatedData;
    $_SESSION['register_error'] =
        $validationError ?? 'ข้อมูลสมาชิกมีการเปลี่ยนแปลง กรุณาตรวจสอบใหม่อีกครั้ง';
    header('Location: register.php');
    exit;
}

try {
    $conflict = findRegistrationConflict(
        $pdo,
        $validatedData['email'],
        $validatedData['student_id']
    );
} catch (Throwable $e) {
    registrationPaymentError('ไม่สามารถตรวจสอบข้อมูลได้ กรุณาลองใหม่อีกครั้ง');
}

if ($conflict !== null) {
    unset($_SESSION['register_step1']);
    $_SESSION['register_old'] = $validatedData;
    $_SESSION['register_error'] = $conflict['message'];
    $_SESSION['register_email_exists'] =
        in_array($conflict['type'], ['email', 'pending'], true);
    header('Location: register.php');
    exit;
}

if (
    !isset($_FILES['payment_slip']) ||
    $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK
) {
    registrationPaymentError('กรุณาแนบหลักฐานการชำระเงิน');
}

$file = $_FILES['payment_slip'];
if (
    !is_uploaded_file($file['tmp_name']) ||
    (int)$file['size'] <= 0 ||
    (int)$file['size'] > 5 * 1024 * 1024
) {
    registrationPaymentError('ไฟล์หลักฐานต้องมีขนาดไม่เกิน 5 MB');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
    registrationPaymentError('รองรับหลักฐานเฉพาะ JPG, PNG, WEBP และ PDF');
}

$uploadDirectory = __DIR__ . '/uploads/payments';
if (
    !is_dir($uploadDirectory) &&
    !mkdir($uploadDirectory, 0775, true) &&
    !is_dir($uploadDirectory)
) {
    registrationPaymentError('ไม่สามารถเตรียมพื้นที่เก็บหลักฐานได้');
}

try {
    $storedName =
        'slip_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
} catch (Throwable $e) {
    registrationPaymentError('ไม่สามารถเตรียมไฟล์หลักฐานได้ กรุณาลองใหม่อีกครั้ง');
}

$fullPath = $uploadDirectory . '/' . $storedName;
$databasePath = 'uploads/payments/' . $storedName;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    registrationPaymentError('ไม่สามารถบันทึกหลักฐานการชำระเงินได้');
}

$membershipFee = 20.00;

try {
    $pdo->beginTransaction();

    // ตรวจซ้ำอีกครั้งภายในกระบวนการบันทึก
    $conflict = findRegistrationConflict(
        $pdo,
        $validatedData['email'],
        $validatedData['student_id']
    );
    if ($conflict !== null) {
        throw new RuntimeException($conflict['message']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name,
            last_name,
            email,
            password,
            role,
            account_status
        )
        VALUES (?, ?, ?, ?, 'user', 'active')
    ");
    $stmt->execute([
        $validatedData['first_name'],
        $validatedData['last_name'],
        $validatedData['email'],
        $step['password_hash'],
    ]);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO privacy_consents (
            user_id,
            email,
            policy_version,
            ip_address,
            user_agent
        )
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $validatedData['email'],
        '2026-09-07',
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO membership_applications (
            user_id,
            student_id,
            faculty,
            major,
            year_level,
            gender,
            phone,
            application_status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'checking_payment')
    ");
    $stmt->execute([
        $userId,
        $validatedData['student_id'],
        $validatedData['faculty'],
        $validatedData['major'],
        $yearLevel,
        $validatedData['gender'] !== '' ? $validatedData['gender'] : null,
        $validatedData['phone'] !== '' ? $validatedData['phone'] : null,
    ]);
    $applicationId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO membership_payments (
            application_id,
            user_id,
            amount,
            payment_method,
            payment_slip,
            payment_status
        )
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $applicationId,
        $userId,
        $membershipFee,
        $paymentMethod,
        $databasePath,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    try {
        $conflict = findRegistrationConflict(
            $pdo,
            $validatedData['email'],
            $validatedData['student_id']
        );
    } catch (Throwable $ignored) {
        $conflict = null;
    }

    if ($conflict !== null) {
        unset($_SESSION['register_step1']);
        $_SESSION['register_old'] = $validatedData;
        $_SESSION['register_error'] = $conflict['message'];
        $_SESSION['register_email_exists'] =
            in_array($conflict['type'], ['email', 'pending'], true);
        header('Location: register.php');
        exit;
    }

    registrationPaymentError('ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['first_name'] = $validatedData['first_name'];
$_SESSION['last_name'] = $validatedData['last_name'];
$_SESSION['email'] = $validatedData['email'];
$_SESSION['profile_image'] = null;
$_SESSION['role'] = 'user';
$_SESSION['membership_success'] =
    'สมัครสมาชิกและส่งหลักฐานการชำระเงิน ' .
    number_format($membershipFee, 2) .
    ' บาทเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบตรวจสอบ';

unset(
    $_SESSION['register_step1'],
    $_SESSION['register_payment_old'],
    $_SESSION['register_payment_error'],
    $_SESSION['register_csrf']
);

header('Location: member_status.php');
exit;
