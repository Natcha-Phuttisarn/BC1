<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['backup_csrf']) || !hash_equals($_SESSION['backup_csrf'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403); exit('คำขอไม่ถูกต้อง');
}

logAdminAction($pdo, 'backup', 'database', null, 'ดาวน์โหลดไฟล์สำรองฐานข้อมูล');
$filename = 'bizcomp-club-backup-' . date('Ymd-His') . '.sql';
header('Content-Type: application/sql; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

echo "-- Bizcomp Club database backup\n-- Created: " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $safeTable = str_replace('`', '``', $table);
    $create = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
    echo "DROP TABLE IF EXISTS `{$safeTable}`;\n" . $create[1] . ";\n\n";
    $rows = $pdo->query("SELECT * FROM `{$safeTable}`", PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $columns = array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
        $values = array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
        echo "INSERT INTO `{$safeTable}` (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
    }
    echo "\n";
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;
