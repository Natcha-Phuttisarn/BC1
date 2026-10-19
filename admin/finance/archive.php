<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['finance_csrf']) || !hash_equals($_SESSION['finance_csrf'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['finance_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'; header('Location: index.php'); exit;
}
$id = (int)($_POST['id'] ?? 0);
$month = preg_match('/^\d{4}-\d{2}$/', $_POST['month'] ?? '') ? $_POST['month'] : date('Y-m');
try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE finance_expenses SET status='archived' WHERE id=? AND status='active'");
        $stmt->execute([$id]);
        logAdminAction($pdo, 'archive', 'finance_expense', $id, 'เก็บรายจ่ายออกจากรายการปัจจุบัน');
        $_SESSION['finance_success'] = 'เก็บรายการรายจ่ายไว้ในข้อมูลถาวรแล้ว';
    } else {
        $start = $month . '-01'; $end = (new DateTime($start))->modify('+1 month')->format('Y-m-d');
        $stmt = $pdo->prepare("UPDATE finance_expenses SET status='archived' WHERE status='active' AND expense_date>=? AND expense_date<?");
        $stmt->execute([$start, $end]);
        logAdminAction($pdo, 'archive', 'finance_expense', null, 'เก็บรายจ่ายทั้งเดือน ' . $month . ' จำนวน ' . $stmt->rowCount() . ' รายการ');
        $_SESSION['finance_success'] = 'เก็บรายจ่ายของเดือน ' . $month . ' จำนวน ' . $stmt->rowCount() . ' รายการไว้แล้ว';
    }
} catch (Throwable $e) { $_SESSION['finance_error'] = $e->getMessage(); }
header('Location: index.php?month=' . rawurlencode($month)); exit;
