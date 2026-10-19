<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// ผู้ดูแลระบบสามารถแก้ไขสถานะผู้สมัครได้โดยตรง

require_once __DIR__ . '/../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$registrationId = (int)($_POST['registration_id'] ?? 0);
$activityId = (int)($_POST['activity_id'] ?? 0);
$newStatus = $_POST['registration_status'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';
$returnSearch = trim($_POST['return_search'] ?? '');
$returnStatus = trim($_POST['return_status'] ?? '');

$returnParams = ['activity_id' => $activityId];
if ($returnSearch !== '') {
    $returnParams['search'] = $returnSearch;
}
if (in_array($returnStatus, ['registered', 'cancelled'], true)) {
    $returnParams['status'] = $returnStatus;
}
$returnUrl = 'index.php?' . http_build_query($returnParams);

if (
    empty($_SESSION['admin_registration_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['admin_registration_csrf'], $csrfToken)
) {
    $_SESSION['registration_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: ' . $returnUrl);
    exit;
}

if (
    $registrationId <= 0 ||
    $activityId <= 0 ||
    !in_array($newStatus, ['registered', 'cancelled'], true)
) {
    $_SESSION['registration_error'] = 'ข้อมูลสถานะผู้สมัครไม่ถูกต้อง';
    header('Location: ' . $returnUrl);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            ar.registration_status,
            a.max_participants,
            (
                SELECT COUNT(*)
                FROM activity_registrations current_ar
                WHERE current_ar.activity_id = ar.activity_id
                  AND current_ar.registration_status = 'registered'
            ) AS registered_count
        FROM activity_registrations ar
        INNER JOIN activities a ON a.id = ar.activity_id
        WHERE ar.id = ? AND ar.activity_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$registrationId, $activityId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new RuntimeException('ไม่พบข้อมูลผู้สมัครที่ต้องการแก้ไข');
    }

    if ($registration['registration_status'] === $newStatus) {
        $pdo->rollBack();
        $_SESSION['registration_success'] = 'สถานะผู้สมัครเป็นค่าที่เลือกอยู่แล้ว';
        header('Location: ' . $returnUrl);
        exit;
    }

    if ($newStatus === 'registered') {
        $maxParticipants = !empty($registration['max_participants'])
            ? (int)$registration['max_participants']
            : null;

        if (
            $maxParticipants !== null &&
            (int)$registration['registered_count'] >= $maxParticipants
        ) {
            throw new RuntimeException('ไม่สามารถคืนสถานะได้ เนื่องจากกิจกรรมมีผู้สมัครเต็มแล้ว');
        }

        $update = $pdo->prepare("
            UPDATE activity_registrations
            SET registration_status = 'registered',
                registered_at = NOW(),
                cancel_reason = NULL,
                cancelled_at = NULL
            WHERE id = ?
        ");
    } else {
        $update = $pdo->prepare("
            UPDATE activity_registrations
            SET registration_status = 'cancelled',
                cancel_reason = 'ยกเลิกโดยผู้ดูแลระบบ',
                cancelled_at = NOW()
            WHERE id = ?
        ");
    }

    $update->execute([$registrationId]);
    $pdo->commit();

    logAdminAction($pdo, 'update_status', 'activity_registration', $registrationId, 'แก้สถานะผู้สมัครกิจกรรมโดยผู้ดูแล', ['status'=>$registration['registration_status']], ['status'=>$newStatus]);

    $_SESSION['registration_success'] = 'บันทึกสถานะผู้สมัครเรียบร้อยแล้ว';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['registration_error'] = $e->getMessage();
}

header('Location: ' . $returnUrl);
exit;
