<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// ลบเฉพาะกิจกรรมที่ไม่มีข้อมูลสัมพันธ์ โดยไม่ให้ Foreign Key Cascade ลบประวัติ
require_once __DIR__ . '/../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$activityId = (int)($_POST['id'] ?? 0);
$csrfToken = (string)($_POST['csrf_token'] ?? '');

if (
    empty($_SESSION['activity_delete_csrf']) ||
    !hash_equals($_SESSION['activity_delete_csrf'], $csrfToken)
) {
    $_SESSION['activity_error'] =
        'คำขอลบกิจกรรมไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

if ($activityId <= 0) {
    $_SESSION['activity_error'] = 'ไม่พบกิจกรรมที่ต้องการลบ';
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // ล็อกกิจกรรมก่อน เพื่อป้องกันการเพิ่มข้อมูลลูกผ่าน Foreign Key
    // ระหว่างตรวจสอบความสัมพันธ์และดำเนินการลบ
    $activityStmt = $pdo->prepare('
        SELECT *
        FROM activities
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ');
    $activityStmt->execute([$activityId]);
    $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        throw new DomainException('ไม่พบกิจกรรมที่ต้องการลบ');
    }

    $relatedChecks = [
        'ผู้สมัครทุกสถานะ' => '
            SELECT id
            FROM activity_registrations
            WHERE activity_id = ?
            FOR UPDATE
        ',
        'ประวัติการเช็กชื่อ' => '
            SELECT id
            FROM activity_attendance
            WHERE activity_id = ?
            FOR UPDATE
        ',
        'ไฟล์แนบกิจกรรม' => '
            SELECT id
            FROM activity_files
            WHERE activity_id = ?
            FOR UPDATE
        ',
        'แกลเลอรีที่เชื่อมโยง' => '
            SELECT id
            FROM activity_gallery
            WHERE activity_id = ?
            FOR UPDATE
        ',
    ];

    $relatedSummary = [];

    foreach ($relatedChecks as $label => $sql) {
        $relatedStmt = $pdo->prepare($sql);
        $relatedStmt->execute([$activityId]);
        $relatedCount = count($relatedStmt->fetchAll(PDO::FETCH_COLUMN));

        if ($relatedCount > 0) {
            $relatedSummary[] = $label . ' ' . $relatedCount . ' รายการ';
        }
    }

    if ($relatedSummary) {
        throw new DomainException(
            'ไม่สามารถลบกิจกรรม "' .
            $activity['title'] .
            '" ได้ เนื่องจากพบ ' .
            implode(', ', $relatedSummary) .
            ' ระบบไม่ได้เปลี่ยนแปลงข้อมูลใด ๆ'
        );
    }

    // ตรวจซ้ำในคำสั่ง DELETE เพื่อไม่ให้ Cascade ทำงาน หากมีข้อมูลสัมพันธ์
    // ปรากฏขึ้นก่อนการลบจริง
    $deleteStmt = $pdo->prepare('
        DELETE a
        FROM activities a
        WHERE a.id = ?
          AND NOT EXISTS (
              SELECT 1
              FROM activity_registrations ar
              WHERE ar.activity_id = a.id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM activity_attendance aa
              WHERE aa.activity_id = a.id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM activity_files af
              WHERE af.activity_id = a.id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM activity_gallery ag
              WHERE ag.activity_id = a.id
          )
    ');
    $deleteStmt->execute([$activityId]);

    if ($deleteStmt->rowCount() !== 1) {
        throw new DomainException(
            'ไม่สามารถลบกิจกรรมได้ เนื่องจากสถานะข้อมูลมีการเปลี่ยนแปลง กรุณาลองใหม่อีกครั้ง'
        );
    }

    $pdo->commit();

    logAdminAction(
        $pdo,
        'delete',
        'activity',
        $activityId,
        'ลบกิจกรรม: ' . $activity['title'],
        $activity,
        null
    );

    // ยกเลิก Token เดิม เพื่อป้องกันการส่งคำขอลบซ้ำ
    unset($_SESSION['activity_delete_csrf']);
    $_SESSION['activity_success'] = 'ลบกิจกรรมเรียบร้อยแล้ว';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException) {
        $_SESSION['activity_error'] = $e->getMessage();
    } else {
        error_log('ACTIVITY DELETE ERROR: ' . $e->getMessage());
        $_SESSION['activity_error'] =
            'ไม่สามารถลบกิจกรรมได้ กรุณาลองใหม่อีกครั้ง';
    }
}

header('Location: index.php');
exit;
