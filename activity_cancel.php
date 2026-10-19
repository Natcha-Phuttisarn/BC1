<?php
require_once __DIR__ . '/includes/bootstrap.php';
// activity_cancel.php
// หน้านี้ใช้สำหรับยกเลิกการสมัครกิจกรรมและบันทึกสาเหตุการยกเลิก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/mail.php';

date_default_timezone_set('Asia/Bangkok');

// =========================================
// ต้องเข้าสู่ระบบก่อน
// =========================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// =========================================
// รับเฉพาะ POST
// =========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: activities.php?my=1');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$registrationId = isset($_POST['registration_id'])
    ? (int)$_POST['registration_id']
    : 0;

$cancelReason = trim($_POST['cancel_reason'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';

// =========================================
// ตรวจสอบ CSRF
// =========================================
if (
    empty($_SESSION['activity_csrf']) ||
    empty($csrfToken) ||
    !hash_equals($_SESSION['activity_csrf'], $csrfToken)
) {
    $_SESSION['activity_error'] =
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';

    header('Location: activities.php?my=1');
    exit;
}

// =========================================
// ตรวจสอบข้อมูล
// =========================================
if ($registrationId <= 0) {
    $_SESSION['activity_error'] =
        'ไม่พบข้อมูลการสมัครกิจกรรม';

    header('Location: activities.php?my=1');
    exit;
}

if ($cancelReason === '') {
    $_SESSION['activity_error'] =
        'กรุณาระบุสาเหตุการยกเลิก';

    header('Location: activities.php?my=1');
    exit;
}

if (mb_strlen($cancelReason, 'UTF-8') > 500) {
    $_SESSION['activity_error'] =
        'สาเหตุการยกเลิกต้องไม่เกิน 500 ตัวอักษร';

    header('Location: activities.php?my=1');
    exit;
}

try {

    // =========================================
    // ตรวจสอบว่ารายการสมัครเป็นของผู้ใช้นี้จริง
    // =========================================
    $stmt = $pdo->prepare("
        SELECT
            ar.id,
            ar.registration_status,
            ar.registered_at,
            a.activity_date,
            a.start_time,
            a.title,
            u.first_name,
            u.last_name,
            u.email
        FROM activity_registrations ar
        INNER JOIN activities a
            ON a.id = ar.activity_id
        INNER JOIN users u ON u.id = ar.user_id
        WHERE ar.id = ?
          AND ar.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $registrationId,
        $userId
    ]);

    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        $_SESSION['activity_error'] =
            'ไม่พบข้อมูลการสมัครกิจกรรม';

        header('Location: activities.php?my=1');
        exit;
    }

    // =========================================
    // ตรวจสอบสถานะปัจจุบัน
    // =========================================
    if ($registration['registration_status'] === 'cancelled') {
        $_SESSION['activity_error'] =
            'รายการนี้ถูกยกเลิกแล้ว';

        header('Location: activities.php?my=1');
        exit;
    }

    if ($registration['registration_status'] !== 'registered') {
        $_SESSION['activity_error'] =
            'ไม่สามารถยกเลิกรายการนี้ได้';

        header('Location: activities.php?my=1');
        exit;
    }

    // ยกเลิกได้ภายใน 48 ชั่วโมงนับจากเวลาที่สมัครเท่านั้น
    if (!empty($registration['registered_at'])) {
        $cancelDeadline = new DateTime($registration['registered_at']);
        $cancelDeadline->modify('+48 hours');

        if (new DateTime() >= $cancelDeadline) {
            $_SESSION['activity_error'] =
                'ไม่สามารถยกเลิกได้ เนื่องจากเกิน 48 ชั่วโมงหลังสมัครแล้ว';

            header('Location: activities.php?my=1');
            exit;
        }
    }

    // =========================================
    // ไม่ให้ยกเลิกหลังเริ่มกิจกรรม
    // =========================================
    if (!empty($registration['activity_date'])) {

        $startTime =
            !empty($registration['start_time'])
            ? $registration['start_time']
            : '00:00:00';

        $activityStart = new DateTime(
            $registration['activity_date'] . ' ' . $startTime
        );

        $now = new DateTime();

        if ($now >= $activityStart) {
            $_SESSION['activity_error'] =
                'ไม่สามารถยกเลิกได้ เนื่องจากกิจกรรมเริ่มแล้ว';

            header('Location: activities.php?my=1');
            exit;
        }
    }

    // =========================================
    // บันทึกการยกเลิก
    // ไม่ DELETE เพื่อให้แอดมินตรวจสอบย้อนหลังได้
    // =========================================
    $update = $pdo->prepare("
        UPDATE activity_registrations
        SET
            registration_status = 'cancelled',
            cancel_reason = ?,
            cancelled_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND registration_status = 'registered'
    ");

    $update->execute([
        $cancelReason,
        $registrationId,
        $userId
    ]);

    if ($update->rowCount() < 1) {
        $_SESSION['activity_error'] =
            'ไม่สามารถยกเลิกการสมัครได้ กรุณาลองใหม่อีกครั้ง';

        header('Location: activities.php?my=1');
        exit;
    }

    sendBizcompNotificationEmail(
        $registration['email'] ?? '',
        trim(($registration['first_name'] ?? '') . ' ' . ($registration['last_name'] ?? '')),
        'ยืนยันการยกเลิกกิจกรรม',
        'ยกเลิกการสมัครกิจกรรมเรียบร้อยแล้ว',
        ['คุณยกเลิกการสมัครกิจกรรม “' . ($registration['title'] ?? '') . '” แล้ว', 'ระบบจะไม่อนุญาตให้สมัครกิจกรรมนี้ซ้ำตามเงื่อนไขของชมรม']
    );

    $_SESSION['activity_success'] =
        'ยกเลิกการสมัครกิจกรรมเรียบร้อยแล้ว และจะไม่สามารถสมัครกิจกรรมนี้ซ้ำได้';

    header('Location: activities.php?my=1');
    exit;

} catch (PDOException $e) {

    $_SESSION['activity_error'] =
        'เกิดข้อผิดพลาดในการยกเลิกกิจกรรม';

    header('Location: activities.php?my=1');
    exit;
}
