<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: membership_apply.php');
    exit;
}

$sessionCsrfToken = $_SESSION['membership_apply_csrf'] ?? null;
$submittedCsrfToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionCsrfToken) ||
    $sessionCsrfToken === '' ||
    !is_string($submittedCsrfToken) ||
    $submittedCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'คำขอไม่ถูกต้อง กรุณากลับไปยังหน้าสมัครสมาชิกและลองใหม่อีกครั้ง';
    exit;
}


$userId = (int)$_SESSION['user_id'];

$studentId = trim($_POST['student_id'] ?? '');
$faculty = trim($_POST['faculty'] ?? '');
$major = trim($_POST['major'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$privacyConsent = isset($_POST['privacy_consent']);


$_SESSION['membership_old'] = [
    'student_id' => $studentId,
    'faculty' => $faculty,
    'major' => $major,
    'gender' => $gender,
    'phone' => $phone
];


/* =========================================
   ตรวจข้อมูล
========================================= */

if (
    $studentId === '' ||
    $faculty === '' ||
    $major === ''
) {

    $_SESSION['membership_error'] =
        'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';

    header('Location: membership_apply.php');
    exit;
}

if (!$privacyConsent) {
    $_SESSION['membership_error'] = 'กรุณายอมรับนโยบายความเป็นส่วนตัวก่อนส่งคำขอ';
    header('Location: membership_apply.php');
    exit;
}

$normalizedPhone = normalizePhoneNumber($phone);

if ($normalizedPhone === false) {
    $_SESSION['membership_error'] =
        'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก';
    header('Location: membership_apply.php');
    exit;
}

$phone = $normalizedPhone ?? '';


/* =========================================
   ตรวจรหัสนิสิต
========================================= */

$studentNumber = preg_replace('/\D/', '', $studentId);

if (strlen($studentNumber) < 2) {

    $_SESSION['membership_error'] =
        'รหัสนิสิตไม่ถูกต้อง';

    header('Location: membership_apply.php');
    exit;
}


/* =========================================
   หาปีที่เข้าเรียนจากรหัสนิสิต
========================================= */

$admissionYear =
    inferAdmissionYearFromStudentId($studentId);


if (!$admissionYear) {

    $_SESSION['membership_error'] =
        'ไม่สามารถตรวจสอบปีที่เข้าเรียนจากรหัสนิสิตได้';

    header('Location: membership_apply.php');
    exit;
}


/* =========================================
   คำนวณชั้นปี
========================================= */

$currentAcademicYear =
    getCurrentAcademicYear();

$yearLevel =
    ($currentAcademicYear - $admissionYear) + 1;


if ($yearLevel < 1 || $yearLevel > 4) {

    $_SESSION['membership_error'] =
        'รหัสนิสิตนี้ไม่อยู่ในช่วงชั้นปี 1 - 4';

    header('Location: membership_apply.php');
    exit;
}


/* =========================================
   ตรวจว่าเป็นสมาชิกแล้วหรือยัง
========================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

if ($stmt->fetch()) {

    unset($_SESSION['membership_old']);

    header('Location: member_status.php');
    exit;
}


/* =========================================
   ตรวจรหัสนิสิตซ้ำในสมาชิก
========================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE student_id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

if ($stmt->fetch()) {

    $_SESSION['membership_error'] =
        'รหัสนิสิตนี้เป็นสมาชิกชมรมอยู่แล้ว';

    header('Location: membership_apply.php');
    exit;
}


/* =========================================
   ตรวจคำขอเดิม
========================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM membership_applications
    WHERE user_id = ?
    AND application_status IN (
        'pending',
        'waiting_payment',
        'checking_payment',
        'approved'
    )
    LIMIT 1
");

$stmt->execute([$userId]);

if ($stmt->fetch()) {

    unset($_SESSION['membership_old']);

    header('Location: member_status.php');
    exit;
}


/* =========================================
   บันทึกคำขอ
========================================= */

try {

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
            ?, ?, ?, ?, ?, ?, ?, 'pending'
        )
    ");

    $stmt->execute([
        $userId,
        $studentId,
        $faculty,
        $major,
        $yearLevel,
        $gender !== '' ? $gender : null,
        $phone !== '' ? $phone : null
    ]);

    $consentStmt = $pdo->prepare("INSERT INTO privacy_consents (user_id, email, policy_version, ip_address, user_agent) SELECT id, email, ?, ?, ? FROM users WHERE id = ?");
    $consentStmt->execute(['2026-09-07', $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $userId]);


    unset($_SESSION['membership_old']);


    $_SESSION['membership_success'] =
        'ส่งคำขอสมัครสมาชิกเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบตรวจสอบ';


    header('Location: member_status.php');
    exit;

} catch (PDOException $e) {

    $_SESSION['membership_error'] =
        'ไม่สามารถส่งคำขอสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง';


    header('Location: membership_apply.php');
    exit;

}
