<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['finance_csrf']) || !hash_equals($_SESSION['finance_csrf'], $_POST['csrf_token'] ?? '')) { $_SESSION['finance_error']='คำขอไม่ถูกต้อง'; header('Location:index.php'); exit; }
$id=(int)($_POST['id']??0);$month=preg_match('/^\d{4}-\d{2}$/',$_POST['month']??'')?$_POST['month']:date('Y-m');
$s=$pdo->prepare("UPDATE finance_expenses SET status='active' WHERE id=? AND status='archived'");$s->execute([$id]);if($s->rowCount()){logAdminAction($pdo,'restore','finance_expense',$id,'ยกเลิกการเก็บถาวรรายจ่าย');$_SESSION['finance_success']='นำรายจ่ายกลับมาในรายการปัจจุบันแล้ว';}else{$_SESSION['finance_error']='ไม่พบรายการที่เก็บถาวร';}header('Location:index.php?month='.rawurlencode($month).'&view=archived');exit;
