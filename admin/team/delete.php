<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// Endpoint เดิมคงไว้เพื่อรองรับหน้าเก่า แต่เปลี่ยนเป็นเก็บประวัติแทนการลบถาวร
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$id = (int)($_POST['id'] ?? 0);
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['team_csrf']) || !hash_equals($_SESSION['team_csrf'], $csrfToken)) {
    $_SESSION['team_error'] = 'คำขอเก็บประวัติไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php'); exit;
}
try {
    $stmt = $pdo->prepare('SELECT * FROM personnel WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) throw new RuntimeException('ไม่พบข้อมูลบุคลากร');
    $update = $pdo->prepare("UPDATE personnel SET status='former', ended_at=COALESCE(ended_at, CURDATE()) WHERE id=?");
    $update->execute([$id]);
    logAdminAction($pdo, 'archive', 'personnel', $id, 'เก็บบุคลากรเป็นประวัติ', $person, ['status'=>'former']);
    $_SESSION['team_success'] = 'เก็บข้อมูลบุคลากรเป็นประวัติเรียบร้อยแล้ว';
} catch (Throwable $e) {
    $_SESSION['team_error'] = $e->getMessage();
}
header('Location: index.php'); exit;
