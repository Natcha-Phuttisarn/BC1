<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['finance_csrf']) || !hash_equals($_SESSION['finance_csrf'], $_POST['csrf_token'] ?? '')) { $_SESSION['finance_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'; header('Location: index.php'); exit; }
$expenseDate = trim($_POST['expense_date'] ?? ''); $category = trim($_POST['category'] ?? ''); $description = trim($_POST['description'] ?? ''); $amount = (float)($_POST['amount'] ?? 0);
$date = DateTime::createFromFormat('!Y-m-d', $expenseDate);
if (!$date || $date->format('Y-m-d') !== $expenseDate || $category === '' || $description === '' || $amount <= 0) { $_SESSION['finance_error'] = 'กรุณากรอกวันที่ รายละเอียด และยอดเงินให้ถูกต้อง'; header('Location: index.php'); exit; }
$savedFile = null;
try {
    if (isset($_FILES['evidence']) && ($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['evidence'];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) throw new RuntimeException('ไฟล์หลักฐานไม่ถูกต้องหรือมีขนาดเกิน 8 MB');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
        if (!isset($allowed[$mime])) throw new RuntimeException('รองรับหลักฐานเฉพาะ JPG, PNG, WEBP และ PDF');
        $dir = __DIR__ . '/../../uploads/finance'; if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์หลักฐานได้');
        $savedFile = 'expense-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $savedFile)) throw new RuntimeException('ไม่สามารถบันทึกไฟล์หลักฐานได้');
    }
    $stmt = $pdo->prepare("INSERT INTO finance_expenses (expense_date, category, description, amount, evidence_file, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$expenseDate, $category, $description, number_format($amount, 2, '.', ''), $savedFile, (int)$_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId(); logAdminAction($pdo, 'create', 'finance_expense', $id, 'บันทึกรายจ่าย: ' . $category, null, ['amount'=>$amount,'expense_date'=>$expenseDate,'description'=>$description]);
    $_SESSION['finance_success'] = 'บันทึกรายจ่ายและคำนวณยอดคงเหลือแล้ว';
} catch (Throwable $e) { if ($savedFile && is_file(__DIR__ . '/../../uploads/finance/' . $savedFile)) @unlink(__DIR__ . '/../../uploads/finance/' . $savedFile); $_SESSION['finance_error'] = $e->getMessage(); }
header('Location: index.php?month=' . rawurlencode(substr($expenseDate, 0, 7))); exit;
