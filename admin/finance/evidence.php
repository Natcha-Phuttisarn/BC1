<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$id = (int)($_GET['id'] ?? 0); $isIncome = ($_GET['type'] ?? '') === 'income'; $table = $isIncome ? 'finance_incomes' : 'finance_expenses'; $stmt = $pdo->prepare("SELECT evidence_file FROM {$table} WHERE id=? AND status IN ('active','archived') LIMIT 1"); $stmt->execute([$id]); $file = $stmt->fetchColumn();
if (!$file || basename($file) !== $file) { http_response_code(404); exit('ไม่พบหลักฐาน'); }
$path = __DIR__ . '/../../uploads/finance/' . $file; if (!is_file($path)) { http_response_code(404); exit('ไม่พบไฟล์'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path); header('Content-Type: ' . $mime); header('Content-Disposition: inline; filename="' . basename($file) . '"'); readfile($path); exit;
