<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// เปลี่ยนเป็นบุคลากรเก่าโดยเก็บประวัติไว้ ไม่ลบข้อมูล

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['team_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['team_csrf'], $csrfToken)
) {
    $_SESSION['team_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

if ($id <= 0) {
    $_SESSION['team_error'] = 'ไม่พบข้อมูลบุคลากร';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE personnel
        SET status = 'former',
            ended_at = COALESCE(ended_at, CURDATE())
        WHERE id = ?
          AND status = 'active'
    ");
    $stmt->execute([$id]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('ไม่พบรายการที่กำลังปฏิบัติหน้าที่');
    }

    logAdminAction($pdo, 'archive', 'personnel', $id, 'สิ้นสุดหน้าที่และเก็บบุคลากรเป็นประวัติ');

    $_SESSION['team_success'] =
        'บันทึกการสิ้นสุดหน้าที่เรียบร้อยแล้ว โดยยังเก็บประวัติบุคลากรไว้';
} catch (Throwable $e) {
    $_SESSION['team_error'] = $e->getMessage();
}

header('Location: index.php');
exit;
