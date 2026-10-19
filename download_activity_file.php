<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fileId <= 0) {
    die('ไม่พบไฟล์');
}

$stmt = $pdo->prepare("SELECT f.*, a.publish_status FROM activity_files f JOIN activities a ON a.id = f.activity_id WHERE f.id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('ไม่พบข้อมูลไฟล์');
}

if ($file['publish_status'] !== 'published') {
    $admin = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND account_status = 'active'");
    $admin->execute([(int)($_SESSION['user_id'] ?? 0)]);
    if (!$admin->fetchColumn()) {
        http_response_code(404);
        exit('ไม่พบข้อมูลไฟล์');
    }
}

$path = realpath(__DIR__ . '/' . ltrim($file['file_path'], '/'));
$uploadRoot = realpath(__DIR__ . '/uploads/activity_files');

if (!$path || !$uploadRoot || !str_starts_with($path, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    die('ไม่พบไฟล์ในระบบ');
}

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
