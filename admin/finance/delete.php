<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['finance_csrf']) || !hash_equals($_SESSION['finance_csrf'], $_POST['csrf_token'] ?? '')) { $_SESSION['finance_error']='คำขอไม่ถูกต้อง'; header('Location:index.php'); exit; }
$id=(int)($_POST['id']??0);$month=preg_match('/^\d{4}-\d{2}$/',$_POST['month']??'')?$_POST['month']:date('Y-m');
try{$s=$pdo->prepare("SELECT * FROM finance_expenses WHERE id=? LIMIT 1");$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('ไม่พบรายจ่าย');$d=$pdo->prepare('DELETE FROM finance_expenses WHERE id=?');$d->execute([$id]);if($row['evidence_file']){$file=__DIR__.'/../../uploads/finance/'.basename($row['evidence_file']);if(is_file($file))@unlink($file);}logAdminAction($pdo,'delete','finance_expense',$id,'ลบรายจ่ายที่บันทึกผิด', $row, null);$_SESSION['finance_success']='ลบรายจ่ายเรียบร้อยแล้ว';}catch(Throwable $e){$_SESSION['finance_error']=$e->getMessage();}header('Location:index.php?month='.rawurlencode($month).'&view='.rawurlencode($_POST['view']??''));exit;
