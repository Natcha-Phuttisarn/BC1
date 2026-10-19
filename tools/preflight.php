<?php
// Run from the hosting terminal: php tools/preflight.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/bootstrap.php';
$failed = false;
function report(bool $ok, string $label): void {
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $failed = true;
}
report(PHP_VERSION_ID >= 80000, 'PHP >= 8.0 (use a supported PHP release in production)');
foreach (['pdo_mysql','mbstring','fileinfo','openssl','session'] as $extension) report(extension_loaded($extension), 'Extension: ' . $extension);
report(is_file(__DIR__.'/../vendor/autoload.php'), 'Composer dependencies installed');
foreach (['profiles','payments','finance','gallery','activity_files','activities/covers'] as $directory) {
    report(is_writable(__DIR__.'/../uploads/'.$directory), 'Writable uploads/'.$directory);
}
report(strlen(bcConfig('BC_OTP_HMAC_SECRET')) >= 64, 'OTP secret configured (>=64 characters)');
report(bcConfig('BC_MAIL_USERNAME') !== '' && bcConfig('BC_MAIL_PASSWORD') !== '', 'SMTP credentials configured (delivery must be tested separately)');
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$sessionPath = substr($sessionPath, strrpos(';'.$sessionPath, ';'));
report(is_dir($sessionPath) && is_writable($sessionPath), 'Session directory writable for CLI user (also check PHP-FPM user)');
require __DIR__ . '/../includes/db.php';
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$schema = file_get_contents(__DIR__.'/../database/hosting_schema.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS `?([a-z_]+)`?/', $schema, $matches);
foreach ($matches[1] as $table) report(in_array($table,$tables,true), 'Table: '.$table);
report((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn()>0, 'Active administrator exists');
echo "No database records were changed. HTTPS, SMTP delivery and web server access rules need an HTTP check.\n";
exit($failed ? 1 : 0);
