<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'ระบบสมัครสมาชิกผ่านช่องทางนี้ถูกยกเลิกแล้ว กรุณาใช้หน้าสมัครสมาชิกปัจจุบัน';
exit;

// register_save.php
// หน้านี้ใช้สำหรับสร้างบัญชี สมัครสมาชิก และบันทึกสลิปการชำระเงินพร้อมกัน

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


// =========================================
// ต้องเข้าผ่าน POST
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: register.php');
    exit;
}


// =========================================
// ค่าสมัครสมาชิก
// ค่าสมัครที่ผู้ดูแลระบบกำหนด
// =========================================

$membershipFee = 20.00;


// =========================================
// รับข้อมูล
// =========================================

$firstName =
    trim($_POST['first_name'] ?? '');

$lastName =
    trim($_POST['last_name'] ?? '');

$studentId =
    trim($_POST['student_id'] ?? '');

$faculty =
    trim($_POST['faculty'] ?? '');

$major =
    trim($_POST['major'] ?? '');

$gender =
    trim($_POST['gender'] ?? '');

$phone =
    trim($_POST['phone'] ?? '');

$email =
    strtolower(
        trim($_POST['email'] ?? '')
    );

$password =
    $_POST['password'] ?? '';

$confirmPassword =
    $_POST['confirm_password'] ?? '';

$paymentMethod =
    trim($_POST['payment_method'] ?? '');

$confirmed =
    isset($_POST['confirm_information']);

$privacyConsent = isset($_POST['privacy_consent']);


// =========================================
// เก็บข้อมูลเดิม
// =========================================

$_SESSION['register_old'] = [

    'first_name' => $firstName,

    'last_name' => $lastName,

    'student_id' => $studentId,

    'faculty' => $faculty,

    'major' => $major,

    'gender' => $gender,

    'phone' => $phone,

    'email' => $email

];


// =========================================
// ตรวจข้อมูลจำเป็น
// =========================================

if (
    $firstName === ''
    ||
    $lastName === ''
    ||
    $studentId === ''
    ||
    $faculty === ''
    ||
    $major === ''
    ||
    $email === ''
    ||
    $password === ''
    ||
    $confirmPassword === ''
    ||
    $paymentMethod === ''
) {

    $_SESSION['register_error'] =
        'กรุณากรอกข้อมูลที่จำเป็นให้ครบทุกช่อง';

    header('Location: register.php');
    exit;
}

$normalizedPhone = normalizePhoneNumber($phone);

if ($normalizedPhone === false) {
    $_SESSION['register_error'] =
        'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก';
    header('Location: register.php');
    exit;
}

$phone = $normalizedPhone ?? '';


// =========================================
// ตรวจยืนยันข้อมูล
// =========================================

if (!$confirmed) {

    $_SESSION['register_error'] =
        'กรุณายืนยันว่าตรวจสอบข้อมูลและชำระเงินเรียบร้อยแล้ว';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจ Email
// =========================================

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    $_SESSION['register_error'] =
        'รูปแบบอีเมลไม่ถูกต้อง';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจ Password
// =========================================

if (strlen($password) < 8) {

    $_SESSION['register_error'] =
        'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';

    header('Location: register.php');
    exit;
}


if ($password !== $confirmPassword) {

    $_SESSION['register_error'] =
        'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจช่องทางชำระเงิน
// =========================================

$allowedPaymentMethods = [
    'promptpay',
    'bank_transfer'
];


if (
    !in_array(
        $paymentMethod,
        $allowedPaymentMethods,
        true
    )
) {

    $_SESSION['register_error'] =
        'ช่องทางการชำระเงินไม่ถูกต้อง';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจรหัสนิสิต
// =========================================

$studentNumber =
    preg_replace(
        '/\D/',
        '',
        $studentId
    );


if (strlen($studentNumber) < 2) {

    $_SESSION['register_error'] =
        'รหัสนิสิตไม่ถูกต้อง';

    header('Location: register.php');
    exit;
}


// =========================================
// คำนวณปีที่เข้าและชั้นปี
// =========================================

$admissionYear =
    inferAdmissionYearFromStudentId(
        $studentId
    );


if (!$admissionYear) {

    $_SESSION['register_error'] =
        'ไม่สามารถตรวจสอบปีที่เข้าเรียนจากรหัสนิสิตได้';

    header('Location: register.php');
    exit;
}


$currentAcademicYear =
    getCurrentAcademicYear();


$yearLevel =
    ($currentAcademicYear - $admissionYear)
    + 1;


if (
    $yearLevel < 1
    ||
    $yearLevel > 4
) {

    $_SESSION['register_error'] =
        'รหัสนิสิตนี้ไม่อยู่ในช่วงชั้นปี 1 - 4';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจเพศ
// =========================================

$allowedGenders = [
    '',
    'ชาย',
    'หญิง',
    'อื่นๆ'
];


if (
    !in_array(
        $gender,
        $allowedGenders,
        true
    )
) {

    $_SESSION['register_error'] =
        'ข้อมูลเพศไม่ถูกต้อง';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจ Email ซ้ำ
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([
    $email
]);


if ($stmt->fetch()) {

    $_SESSION['register_error'] =
        'อีเมลนี้มีบัญชีอยู่ในระบบแล้ว หากเคยสมัครไว้กรุณาเข้าสู่ระบบด้วยบัญชีเดิม';

    $_SESSION['register_email_exists'] =
        true;

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจรหัสนิสิตใน Members
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE student_id = ?
    LIMIT 1
");

$stmt->execute([
    $studentId
]);


if ($stmt->fetch()) {

    $_SESSION['register_error'] =
        'รหัสนิสิตนี้เป็นสมาชิกชมรมอยู่แล้ว กรุณาเข้าสู่ระบบด้วยบัญชีเดิม';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจรหัสนิสิตในคำขอสมัคร
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM membership_applications
    WHERE student_id = ?
    AND application_status IN (
        'pending',
        'waiting_payment',
        'checking_payment',
        'approved'
    )
    LIMIT 1
");

$stmt->execute([
    $studentId
]);


if ($stmt->fetch()) {

    $_SESSION['register_error'] =
        'รหัสนิสิตนี้มีคำขอสมัครสมาชิกอยู่ในระบบแล้ว กรุณาใช้บัญชีเดิมเพื่อตรวจสอบสถานะ';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจไฟล์สลิป
// =========================================

if (
    !isset($_FILES['payment_slip'])
    ||
    $_FILES['payment_slip']['error']
        !== UPLOAD_ERR_OK
) {

    $_SESSION['register_error'] =
        'กรุณาแนบหลักฐานการชำระเงิน';

    header('Location: register.php');
    exit;
}


$file =
    $_FILES['payment_slip'];


// =========================================
// ตรวจขนาดไฟล์
// =========================================

if (
    $file['size']
    > 5 * 1024 * 1024
) {

    $_SESSION['register_error'] =
        'ไฟล์หลักฐานการชำระเงินต้องมีขนาดไม่เกิน 5 MB';

    header('Location: register.php');
    exit;
}


// =========================================
// ตรวจ MIME
// =========================================

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );

$mimeType =
    $finfo->file(
        $file['tmp_name']
    );


$allowedTypes = [

    'image/jpeg' => 'jpg',

    'image/png' => 'png',

    'image/webp' => 'webp',

    'application/pdf' => 'pdf'

];


if (
    !isset(
        $allowedTypes[$mimeType]
    )
) {

    $_SESSION['register_error'] =
        'รองรับหลักฐานเฉพาะ JPG, PNG, WEBP และ PDF';

    header('Location: register.php');
    exit;
}


// =========================================
// เตรียม Upload
// =========================================

$uploadDirectory =
    __DIR__
    . '/uploads/payments';


if (!is_dir($uploadDirectory)) {

    if (
        !mkdir(
            $uploadDirectory,
            0775,
            true
        )
        &&
        !is_dir($uploadDirectory)
    ) {

        $_SESSION['register_error'] =
            'ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บหลักฐานได้';

        header('Location: register.php');
        exit;
    }
}


// =========================================
// ตั้งชื่อไฟล์ใหม่
// =========================================

try {

    $randomName =
        bin2hex(
            random_bytes(16)
        );

} catch (Throwable $e) {

    $randomName =
        uniqid(
            'slip_',
            true
        );
}


$extension =
    $allowedTypes[$mimeType];


$storedName =
    'slip_'
    . $randomName
    . '.'
    . $extension;


$fullPath =
    $uploadDirectory
    . '/'
    . $storedName;


$databasePath =
    'uploads/payments/'
    . $storedName;


// =========================================
// Upload ไฟล์ก่อนบันทึก DB
// =========================================

if (
    !move_uploaded_file(
        $file['tmp_name'],
        $fullPath
    )
) {

    $_SESSION['register_error'] =
        'ไม่สามารถอัปโหลดหลักฐานการชำระเงินได้';

    header('Location: register.php');
    exit;
}


// =========================================
// บันทึกทุกอย่างพร้อมกัน
// =========================================

try {

    $pdo->beginTransaction();


    // =====================================
    // 1. CREATE USER
    // =====================================

    $hashedPassword =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );


    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name,
            last_name,
            email,
            password,
            role,
            account_status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            'user',
            'active'
        )
    ");


    $stmt->execute([

        $firstName,

        $lastName,

        $email,

        $hashedPassword

    ]);


    $userId =
        (int)$pdo->lastInsertId();

    $consentStmt = $pdo->prepare("INSERT INTO privacy_consents (user_id, email, policy_version, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $consentStmt->execute([$userId, $email, '2026-09-07', $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);


    // =====================================
    // 2. CREATE MEMBERSHIP APPLICATION
    // =====================================

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
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'checking_payment'
        )
    ");


    $stmt->execute([

        $userId,

        $studentId,

        $faculty,

        $major,

        $yearLevel,

        $gender !== ''
            ? $gender
            : null,

        $phone !== ''
            ? $phone
            : null

    ]);


    $applicationId =
        (int)$pdo->lastInsertId();


    // =====================================
    // 3. CREATE PAYMENT
    // =====================================

    $stmt = $pdo->prepare("
        INSERT INTO membership_payments (
            application_id,
            user_id,
            amount,
            payment_method,
            payment_slip,
            payment_status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending'
        )
    ");


    $stmt->execute([

        $applicationId,

        $userId,

        $membershipFee,

        $paymentMethod,

        $databasePath

    ]);


    // =====================================
    // COMMIT
    // =====================================

    $pdo->commit();


    // =====================================
    // AUTO LOGIN
    // =====================================

    session_regenerate_id(true);


    $_SESSION['user_id'] =
        $userId;

    $_SESSION['first_name'] =
        $firstName;

    $_SESSION['last_name'] =
        $lastName;

    $_SESSION['email'] =
        $email;

    $_SESSION['profile_image'] =
        null;

    $_SESSION['role'] =
        'user';


    unset(
        $_SESSION['register_old']
    );


    $_SESSION['membership_success'] =
        'สมัครสมาชิกและส่งหลักฐานการชำระเงิน ' . number_format($membershipFee, 2) . ' บาทเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบตรวจสอบ';


    header(
        'Location: member_status.php'
    );

    exit;


} catch (Throwable $e) {


    // =====================================
    // Rollback
    // =====================================

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    // ลบสลิปที่ Upload แล้ว
    if (is_file($fullPath)) {

        unlink($fullPath);
    }


    $_SESSION['register_error'] =
        'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง';


    header('Location: register.php');
    exit;

}

if (!$privacyConsent) {
    $_SESSION['register_error'] = 'กรุณายอมรับนโยบายความเป็นส่วนตัวก่อนสมัครสมาชิก';
    header('Location: register.php');
    exit;
}
