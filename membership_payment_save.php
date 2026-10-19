<?php
require_once __DIR__ . '/includes/bootstrap.php';
// membership_payment_save.php
// หน้านี้ใช้สำหรับบันทึกข้อมูลและส่งหลักฐานการชำระเงินเพื่อขอตรวจสอบใหม่

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


// =========================================
// LOGIN
// =========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


// =========================================
// POST
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: member_status.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];


// =========================================
// ERROR FUNCTION
// =========================================

function retryPaymentError(string $message): void
{
    $_SESSION['payment_submit_error'] = $message;

    header('Location: membership_payment.php');
    exit;
}


// =========================================
// CSRF
// =========================================

$csrfToken =
    $_POST['csrf_token']
    ?? '';

$sessionToken =
    $_SESSION['payment_retry_csrf']
    ?? '';

if (
    $sessionToken === ''
    ||
    !hash_equals(
        $sessionToken,
        $csrfToken
    )
) {

    retryPaymentError(
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'
    );
}


// =========================================
// รับข้อมูล
// =========================================

$applicationId =
    (int)($_POST['application_id'] ?? 0);

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

$paymentMethod =
    trim($_POST['payment_method'] ?? '');


// =========================================
// ค่าสมัครที่ผู้ดูแลระบบกำหนด
// =========================================

$amount = 20.00;


// =========================================
// เก็บข้อมูลเดิม
// =========================================

$_SESSION['payment_retry_old'] = [

    'student_id' =>
        $studentId,

    'faculty' =>
        $faculty,

    'major' =>
        $major,

    'gender' =>
        $gender,

    'phone' =>
        $phone,

    'payment_method' =>
        $paymentMethod

];


// =========================================
// VALIDATION
// =========================================

if (
    $applicationId <= 0
    ||
    $studentId === ''
    ||
    $faculty === ''
    ||
    $major === ''
    ||
    $paymentMethod === ''
) {

    retryPaymentError(
        'กรุณากรอกข้อมูลที่จำเป็นให้ครบ'
    );
}

$normalizedPhone = normalizePhoneNumber($phone);

if ($normalizedPhone === false) {
    retryPaymentError(
        'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก'
    );
}

$phone = $normalizedPhone ?? '';


// =========================================
// PAYMENT METHOD
// =========================================

if (
    !in_array(
        $paymentMethod,
        [
            'promptpay',
            'bank_transfer'
        ],
        true
    )
) {

    retryPaymentError(
        'ช่องทางการชำระเงินไม่ถูกต้อง'
    );
}


// =========================================
// GENDER
// =========================================

if (
    !in_array(
        $gender,
        [
            '',
            'ชาย',
            'หญิง',
            'อื่นๆ'
        ],
        true
    )
) {

    retryPaymentError(
        'ข้อมูลเพศไม่ถูกต้อง'
    );
}


// =========================================
// STUDENT ID
// =========================================

$studentNumber =
    preg_replace(
        '/\D/',
        '',
        $studentId
    );

if (
    $studentNumber === ''
    ||
    strlen($studentNumber) < 2
) {

    retryPaymentError(
        'รหัสนิสิตไม่ถูกต้อง'
    );
}


// =========================================
// ADMISSION YEAR
// =========================================

$admissionYear =
    inferAdmissionYearFromStudentId(
        $studentId
    );

if (!$admissionYear) {

    retryPaymentError(
        'ไม่สามารถตรวจสอบปีที่เข้าเรียนจากรหัสนิสิตได้'
    );
}


// =========================================
// YEAR LEVEL
// =========================================

$currentAcademicYear =
    getCurrentAcademicYear();

$yearLevel =
    ($currentAcademicYear - $admissionYear) + 1;

if (
    $yearLevel < 1
    ||
    $yearLevel > 4
) {

    retryPaymentError(
        'รหัสนิสิตนี้ไม่อยู่ในช่วงชั้นปี 1 - 4'
    );
}


// =========================================
// ตรวจ Application
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM membership_applications
    WHERE id = ?
    AND user_id = ?
    AND application_status IN (
        'waiting_payment',
        'rejected'
    )
    LIMIT 1
");

$stmt->execute([
    $applicationId,
    $userId
]);

$application = $stmt->fetch();


if (!$application) {

    retryPaymentError(
        'คำขอนี้ไม่สามารถส่งตรวจใหม่ได้'
    );
}


// =========================================
// ห้ามสมาชิกปัจจุบันส่งใหม่
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

if ($stmt->fetch()) {

    unset($_SESSION['payment_retry_old']);

    header('Location: member_status.php');
    exit;
}


// =========================================
// ตรวจรหัสนิสิตซ้ำใน members
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

    retryPaymentError(
        'รหัสนิสิตนี้มีสมาชิกอยู่ในระบบแล้ว'
    );
}


// =========================================
// ตรวจคำขอของบัญชีอื่น
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM membership_applications
    WHERE student_id = ?
    AND user_id != ?
    AND application_status IN (
        'pending',
        'waiting_payment',
        'checking_payment',
        'approved'
    )
    LIMIT 1
");

$stmt->execute([
    $studentId,
    $userId
]);

if ($stmt->fetch()) {

    retryPaymentError(
        'รหัสนิสิตนี้ถูกใช้กับคำขออื่นอยู่แล้ว'
    );
}


// =========================================
// PAYMENT เดิม
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM membership_payments
    WHERE application_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $applicationId
]);

$lastPayment =
    $stmt->fetch();


// =========================================
// ตรวจไฟล์ใหม่
// =========================================

$hasNewFile = false;

if (isset($_FILES['payment_slip'])) {

    if (
        $_FILES['payment_slip']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {

        $hasNewFile = true;
    }
}


// =========================================
// PATH ที่จะบันทึก
// =========================================

$databasePath = '';

$newUploadedFile = '';


// =========================================
// มีไฟล์ใหม่
// =========================================

if ($hasNewFile) {

    $file =
        $_FILES['payment_slip'];


    if (
        $file['error']
        !== UPLOAD_ERR_OK
    ) {

        retryPaymentError(
            'ไม่สามารถอัปโหลดหลักฐานได้'
        );
    }


    // ต้องเป็นไฟล์ที่อัปโหลดผ่าน HTTP และมีขนาดไม่เกิน 5 MB

    if (
        !isset($file['tmp_name'])
        ||
        !is_string($file['tmp_name'])
        ||
        !is_uploaded_file($file['tmp_name'])
        ||
        (int)$file['size'] <= 0
        ||
        (int)$file['size'] > 5 * 1024 * 1024
    ) {

        retryPaymentError(
            'ไฟล์หลักฐานต้องมีขนาดไม่เกิน 5 MB'
        );
    }


    // =====================================
    // MIME
    // =====================================

    $finfo =
        new finfo(
            FILEINFO_MIME_TYPE
        );

    $mimeType =
        $finfo->file(
            $file['tmp_name']
        );

    $allowedMimeTypes = [

        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'

    ];


    if (
        !is_string($mimeType)
        ||
        !isset($allowedMimeTypes[$mimeType])
    ) {

        retryPaymentError(
            'รองรับเฉพาะ JPG, PNG, WEBP และ PDF'
        );
    }


    // =====================================
    // UPLOAD
    // =====================================

    $uploadDirectory =
        __DIR__ . '/uploads/payments';


    if (
        !is_dir($uploadDirectory)
        &&
        !mkdir($uploadDirectory, 0775, true)
        &&
        !is_dir($uploadDirectory)
    ) {

        retryPaymentError(
            'ไม่สามารถเตรียมพื้นที่เก็บหลักฐานได้'
        );
    }


    try {

        $storedName =
            'slip_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $allowedMimeTypes[$mimeType];

    } catch (Throwable $e) {

        retryPaymentError(
            'ไม่สามารถเตรียมไฟล์หลักฐานได้ กรุณาลองใหม่อีกครั้ง'
        );
    }


    $databasePath =
        'uploads/payments/' .
        $storedName;

    $newUploadedFile =
        $uploadDirectory .
        '/' .
        $storedName;


    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $newUploadedFile
        )
    ) {

        retryPaymentError(
            'ไม่สามารถบันทึกไฟล์หลักฐานได้'
        );
    }

}


// =========================================
// ไม่มีไฟล์ใหม่
// ใช้สลิปเดิมได้เฉพาะกรณี rejected
// =========================================

else {


    if (
        $application['application_status']
        === 'rejected'
        &&
        $lastPayment
        &&
        !empty(
            $lastPayment['payment_slip']
        )
    ) {

        $databasePath =
            $lastPayment['payment_slip'];

    } else {

        retryPaymentError(
            'กรุณาแนบหลักฐานการชำระเงิน'
        );
    }

}


// =========================================
// TRANSACTION
// =========================================

try {

    $pdo->beginTransaction();


    // =====================================
    // UPDATE APPLICATION
    // =====================================

    $stmt = $pdo->prepare("
        UPDATE membership_applications
        SET
            student_id = ?,
            faculty = ?,
            major = ?,
            year_level = ?,
            gender = ?,
            phone = ?,
            application_status = 'checking_payment',
            admin_note = NULL
        WHERE id = ?
        AND user_id = ?
        AND application_status IN (
            'waiting_payment',
            'rejected'
        )
    ");

    $stmt->execute([

        $studentId,

        $faculty,

        $major,

        $yearLevel,

        $gender !== ''
            ? $gender
            : null,

        $phone !== ''
            ? $phone
            : null,

        $applicationId,

        $userId

    ]);


    if ($stmt->rowCount() < 1) {

        throw new RuntimeException(
            'สถานะคำขอมีการเปลี่ยนแปลง กรุณาลองใหม่อีกครั้ง'
        );
    }


    // =====================================
    // สร้าง PAYMENT ใหม่
    // ไม่เขียนทับรายการเดิม
    // =====================================

    $stmt = $pdo->prepare("
        INSERT INTO membership_payments (
            application_id,
            user_id,
            amount,
            payment_method,
            payment_slip,
            payment_status,
            admin_note
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending',
            NULL
        )
    ");

    $stmt->execute([

        $applicationId,

        $userId,

        $amount,

        $paymentMethod,

        $databasePath

    ]);


    $pdo->commit();


    // =====================================
    // SUCCESS
    // =====================================

    unset(
        $_SESSION['payment_retry_old']
    );


    $_SESSION['membership_success'] =
        'ส่งข้อมูลและหลักฐานเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบตรวจสอบอีกครั้ง';


    header(
        'Location: member_status.php'
    );

    exit;


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    // ลบเฉพาะไฟล์ใหม่
    // ห้ามลบไฟล์เก่าที่นำกลับมาใช้

    if (
        $newUploadedFile !== ''
        &&
        is_file($newUploadedFile)
    ) {

        @unlink(
            $newUploadedFile
        );
    }


    retryPaymentError(
        'ไม่สามารถส่งคำขอได้ กรุณาลองใหม่อีกครั้ง'
    );
}
