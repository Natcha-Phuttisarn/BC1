<?php

require_once __DIR__ . '/bootstrap.php';
$host = bcConfig('BC_DB_HOST', 'localhost');
$port = bcConfig('BC_DB_PORT', '3306');
$dbname = bcConfig('BC_DB_NAME');
$username = bcConfig('BC_DB_USERNAME');
$password = bcConfig('BC_DB_PASSWORD');

if (!preg_match('/^[A-Za-z0-9_.:-]+$/D', $host) || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
    || !preg_match('/^[A-Za-z0-9_\-]+$/D', $dbname) || $username === '') {
    http_response_code(503);
    error_log('BC1 database environment is not configured.');
    if (PHP_SAPI === 'cli') { exit(1); }
    exit('ไม่สามารถเชื่อมต่อระบบฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    http_response_code(503);
    error_log('BC1 database connection failed.');
    if (PHP_SAPI === 'cli') { exit(1); }
    exit('ไม่สามารถเชื่อมต่อระบบฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง');
}
