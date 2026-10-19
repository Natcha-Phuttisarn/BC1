<?php
require_once __DIR__ . '/includes/bootstrap.php';
// activity_register.php
// หน้านี้ใช้สำหรับบันทึกการสมัครกิจกรรม
// และปิดรับอัตโนมัติเมื่อเต็มหรือหมดเขต

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/mail.php';
require_once 'includes/functions.php';

date_default_timezone_set('Asia/Bangkok');


// =========================================
// ต้องเข้าสู่ระบบก่อน
// =========================================

if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['activity_error'] =
        'กรุณายืนยันการสมัครกิจกรรมจากหน้ารายละเอียด';
    header('Location: activities.php');
    exit;
}


$userId =
    (int)$_SESSION['user_id'];


// =========================================
// รับรหัสกิจกรรม
// =========================================

$activityId =
    isset($_POST['activity_id'])
    ? (int)$_POST['activity_id']
    : 0;

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['activity_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['activity_csrf'], $csrfToken)
) {
    $_SESSION['activity_error'] =
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: activities.php');
    exit;
}


if ($activityId <= 0) {

    $_SESSION['activity_error'] =
        'ไม่พบกิจกรรมที่ต้องการสมัคร';

    header('Location: activities.php');
    exit;
}


$backUrl =
    'activities.php?id=' .
    $activityId;


// =========================================
// เริ่มสมัครกิจกรรม
// =========================================

try {

    $pdo->beginTransaction();


    // =========================================
    // ตรวจสิทธิ์สมาชิกจากฐานข้อมูลจริง
    // ต้องเป็นสมาชิกที่ใช้งานได้และคำขอล่าสุดอนุมัติแล้ว
    // =========================================

    $memberStmt = $pdo->prepare("
        SELECT
            u.account_status,
            m.*
        FROM users u
        LEFT JOIN members m
            ON m.user_id = u.id
        WHERE u.id = ?
        FOR UPDATE
    ");

    $memberStmt->execute([$userId]);

    $memberAccess =
        $memberStmt->fetch(PDO::FETCH_ASSOC);

    $applicationStmt = $pdo->prepare("
        SELECT application_status
        FROM membership_applications
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $applicationStmt->execute([$userId]);

    $latestApplicationStatus =
        $applicationStmt->fetchColumn();

    $automaticMemberStatus =
        $memberAccess && !empty($memberAccess['id'])
        ? getAutomaticMemberStatus($memberAccess)
        : null;

    $canRegisterAsMember =
        $memberAccess &&
        !empty($memberAccess['id']) &&
        ($memberAccess['account_status'] ?? '') === 'active' &&
        ($memberAccess['member_status'] ?? '') === 'active' &&
        $latestApplicationStatus === 'approved' &&
        ($automaticMemberStatus['code'] ?? '') === 'active';

    if (!$canRegisterAsMember) {
        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'คุณยังไม่ได้รับการอนุมัติเป็นสมาชิกชมรม จึงไม่สามารถลงทะเบียนกิจกรรมได้';

        header('Location: ' . $backUrl);
        exit;
    }


    // =========================================
    // ล็อกกิจกรรม
    // ป้องกันคนสมัครพร้อมกันจนเกินจำนวน
    // =========================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            registration_open,
            registration_close,
            max_participants,
            publish_status

        FROM activities

        WHERE id = ?

        FOR UPDATE
    ");

    $stmt->execute([
        $activityId
    ]);

    $activity =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    // =========================================
    // ไม่พบกิจกรรม
    // =========================================

    if (!$activity) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'ไม่พบกิจกรรมนี้';

        header(
            'Location: activities.php'
        );

        exit;
    }


    // =========================================
    // กิจกรรมยังไม่เผยแพร่
    // =========================================

    if (
        ($activity['publish_status'] ?? '')
        !== 'published'
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'กิจกรรมนี้ยังไม่ได้เปิดให้สมัคร';

        header(
            'Location: ' . $backUrl
        );

        exit;
    }


    // =========================================
    // ตรวจวันและเวลา
    // =========================================

    $now =
        new DateTime();


    $open =
        !empty(
            $activity['registration_open']
        )
        ? new DateTime(
            $activity['registration_open']
        )
        : null;


    $close =
        !empty(
            $activity['registration_close']
        )
        ? new DateTime(
            $activity['registration_close']
        )
        : null;


    // =========================================
    // ยังไม่เปิดรับสมัคร
    // =========================================

    if (
        $open !== null &&
        $now < $open
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'กิจกรรมนี้ยังไม่เปิดรับสมัคร';

        header(
            'Location: ' . $backUrl
        );

        exit;
    }


    // =========================================
    // หมดเขตรับสมัคร
    // =========================================

    if (
        $close !== null &&
        $now >= $close
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'กิจกรรมนี้หมดเขตรับสมัครแล้ว';

        header(
            'Location: ' . $backUrl
        );

        exit;
    }


    // =========================================
    // ตรวจว่าเคยสมัครหรือยัง
    // =========================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            registration_status

        FROM activity_registrations

        WHERE activity_id = ?

        AND user_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $activityId,
        $userId
    ]);

    $existingRegistration =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    // สมัครอยู่แล้ว
    if (
        $existingRegistration &&
        (
            $existingRegistration[
                'registration_status'
            ] ?? ''
        ) === 'registered'
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'คุณสมัครกิจกรรมนี้แล้ว';

        header(
            'Location: ' . $backUrl
        );

        exit;
    }

    // เมื่อผู้ใช้ยกเลิกแล้ว จะสมัครกิจกรรมเดิมซ้ำไม่ได้
    if (
        $existingRegistration &&
        ($existingRegistration['registration_status'] ?? '') === 'cancelled'
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'คุณเคยยกเลิกกิจกรรมนี้แล้ว จึงไม่สามารถสมัครซ้ำได้';

        header('Location: ' . $backUrl);
        exit;
    }


    // =========================================
    // นับจำนวนผู้สมัครปัจจุบัน
    // =========================================

    $stmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM activity_registrations

        WHERE activity_id = ?

        AND registration_status =
            'registered'
    ");

    $stmt->execute([
        $activityId
    ]);

    $registeredCount =
        (int)$stmt->fetchColumn();


    // =========================================
    // จำนวนรับสูงสุด
    // =========================================

    $maxParticipants =
        !empty(
            $activity['max_participants']
        )
        ? (int)$activity['max_participants']
        : null;


    // =========================================
    // เต็มแล้ว ปิดรับทันที
    // =========================================

    if (
        $maxParticipants !== null &&
        $registeredCount >=
            $maxParticipants
    ) {

        $pdo->rollBack();

        $_SESSION['activity_error'] =
            'กิจกรรมนี้มีผู้สมัครเต็มแล้ว';

        header(
            'Location: ' . $backUrl
        );

        exit;
    }


    // สมัครครั้งแรกเท่านั้น
    $stmt = $pdo->prepare("
        INSERT INTO activity_registrations
        (
            activity_id,
            user_id,
            registration_status
        )
        VALUES (?, ?, 'registered')
    ");

    $stmt->execute([
        $activityId,
        $userId
    ]);


    // =========================================
    // สำเร็จ
    // =========================================

    $pdo->commit();

    $userStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    sendBizcompNotificationEmail(
        $user['email'] ?? '',
        trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'ยืนยันการสมัครกิจกรรม',
        'สมัครกิจกรรมเรียบร้อยแล้ว',
        ['คุณสมัครกิจกรรม “' . $activity['title'] . '” สำเร็จแล้ว', 'สามารถยกเลิกได้ภายใน 48 ชั่วโมงหลังสมัคร หากเกินกำหนดจะไม่สามารถยกเลิกได้', 'หากยกเลิกแล้วจะไม่สามารถสมัครกิจกรรมนี้ซ้ำได้']
    );


    $_SESSION['activity_success'] =
        'สมัครเข้าร่วมกิจกรรมเรียบร้อยแล้ว คุณสามารถยกเลิกได้ภายใน 48 ชั่วโมงหลังสมัคร';


    header(
        'Location: ' . $backUrl
    );

    exit;


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    error_log(
        'ACTIVITY REGISTER ERROR: ' .
        $e->getMessage()
    );


    $_SESSION['activity_error'] =
        'ไม่สามารถสมัครกิจกรรมได้ กรุณาลองใหม่อีกครั้ง';


    header(
        'Location: ' . $backUrl
    );

    exit;
}
