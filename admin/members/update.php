<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sessionCsrfToken = $_SESSION['admin_member_csrf'] ?? null;
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
    echo 'คำขอไม่ถูกต้อง กรุณากลับไปยังหน้าแก้ไขสมาชิกและลองใหม่อีกครั้ง';
    exit;
}

$memberId = (int)($_POST['member_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$studentId = trim($_POST['student_id'] ?? '');
$admissionYear = (int)($_POST['admission_year'] ?? 0);
$faculty = trim($_POST['faculty'] ?? '');
$major = trim($_POST['major'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$memberStatus = $_POST['member_status'] ?? 'active';
$accountStatus = $_POST['account_status'] ?? 'active';
$joinedAt = $_POST['joined_at'] ?? '';

/* ตรวจข้อมูล */
if (
    $memberId <= 0 ||
    $userId <= 0 ||
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $studentId === '' ||
    $admissionYear <= 0
) {
    $_SESSION['member_error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';
    header('Location: edit.php?id=' . $memberId);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['member_error'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    header('Location: edit.php?id=' . $memberId);
    exit;
}

$normalizedPhone = normalizePhoneNumber($phone);

if ($normalizedPhone === false) {
    $_SESSION['member_error'] =
        'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก';
    header('Location: edit.php?id=' . $memberId);
    exit;
}

$phone = $normalizedPhone ?? '';

if ($admissionYear < 2500 || $admissionYear > 2700) {
    $_SESSION['member_error'] = 'ปีที่เข้าเรียนไม่ถูกต้อง';
    header('Location: edit.php?id=' . $memberId);
    exit;
}

/* สถานะที่อนุญาต */
$allowedMemberStatus = ['active', 'inactive', 'suspended'];
$allowedAccountStatus = ['active', 'inactive', 'blocked'];

if (!in_array($memberStatus, $allowedMemberStatus, true)) {
    $memberStatus = 'active';
}

if (!in_array($accountStatus, $allowedAccountStatus, true)) {
    $accountStatus = 'active';
}

try {

    $oldStmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.email, u.account_status FROM members m INNER JOIN users u ON u.id=m.user_id WHERE m.id=? LIMIT 1");
    $oldStmt->execute([$memberId]);
    $oldMember = $oldStmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldMember) throw new Exception('ไม่พบข้อมูลสมาชิก');

    /* เช็กอีเมลซ้ำ */
    $checkEmail = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        AND id != ?
        LIMIT 1
    ");

    $checkEmail->execute([$email, $userId]);

    if ($checkEmail->fetch()) {
        throw new Exception('อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว');
    }

    /* เช็กรหัสนิสิตซ้ำ */
    $checkStudent = $pdo->prepare("
        SELECT id
        FROM members
        WHERE student_id = ?
        AND id != ?
        LIMIT 1
    ");

    $checkStudent->execute([$studentId, $memberId]);

    if ($checkStudent->fetch()) {
        throw new Exception('รหัสนิสิตนี้มีอยู่ในระบบแล้ว');
    }

    $pdo->beginTransaction();

    /* อัปเดต Users */
    $userStmt = $pdo->prepare("
        UPDATE users
        SET
            first_name = ?,
            last_name = ?,
            email = ?,
            account_status = ?
        WHERE id = ?
    ");

    $userStmt->execute([
        $firstName,
        $lastName,
        $email,
        $accountStatus,
        $userId
    ]);

    /* อัปเดต Members */
    $memberStmt = $pdo->prepare("
        UPDATE members
        SET
            student_id = ?,
            admission_year = ?,
            faculty = ?,
            major = ?,
            gender = ?,
            phone = ?,
            member_status = ?,
            joined_at = ?
        WHERE id = ?
    ");

    $memberStmt->execute([
        $studentId,
        $admissionYear,
        $faculty !== '' ? $faculty : null,
        $major !== '' ? $major : null,
        $gender !== '' ? $gender : null,
        $phone !== '' ? $phone : null,
        $memberStatus,
        $joinedAt !== '' ? $joinedAt : null,
        $memberId
    ]);

    $pdo->commit();

    logAdminAction($pdo, 'update', 'member', $memberId, 'แก้ไขข้อมูลสมาชิก: ' . $studentId, $oldMember, [
        'student_id'=>$studentId, 'email'=>$email, 'phone'=>$phone, 'member_status'=>$memberStatus, 'account_status'=>$accountStatus
    ]);

    $_SESSION['member_success'] = 'แก้ไขข้อมูลสมาชิกเรียบร้อยแล้ว';

    header('Location: index.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['member_error'] = $e->getMessage();

    header('Location: edit.php?id=' . $memberId);
    exit;
}
