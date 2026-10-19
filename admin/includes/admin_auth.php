<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header(('Location: ' . BC_BASE_PATH . '/admin/login.php'));
    exit;
}

// ตรวจสิทธิ์กับฐานข้อมูลทุกครั้ง เพื่อให้การแก้ไข role/status มีผลจริงทันที
$adminAuthStmt = $pdo->prepare("
    SELECT first_name, last_name, email, profile_image, role, account_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$adminAuthStmt->execute([(int)$_SESSION['user_id']]);
$adminAuthUser = $adminAuthStmt->fetch(PDO::FETCH_ASSOC);

if (
    !$adminAuthUser ||
    ($adminAuthUser['role'] ?? '') !== 'admin' ||
    ($adminAuthUser['account_status'] ?? '') !== 'active'
) {
    $_SESSION = [];
    session_destroy();
    header(('Location: ' . BC_BASE_PATH . '/admin/login.php'));
    exit;
}

$_SESSION['first_name'] = $adminAuthUser['first_name'];
$_SESSION['last_name'] = $adminAuthUser['last_name'];
$_SESSION['email'] = $adminAuthUser['email'];
$_SESSION['profile_image'] = $adminAuthUser['profile_image'];
$_SESSION['role'] = $adminAuthUser['role'];
