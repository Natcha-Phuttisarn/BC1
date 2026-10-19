<?php
// CLI only; password is read from standard input, never command arguments.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/db.php';
if ($pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn()) {
    fwrite(STDERR, "An administrator already exists. Use the administration interface.\n"); exit(1);
}
$email = trim($argv[1] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>150) {
    fwrite(STDERR, "Usage: php tools/create_admin.php admin@example.com (password via stdin)\n"); exit(1);
}
fwrite(STDERR, "Enter a new password (input may be visible in this terminal): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password)<12) { fwrite(STDERR,"Use at least 12 characters.\n"); exit(1); }
$stmt=$pdo->prepare("INSERT INTO users(first_name,last_name,email,password,role,account_status) VALUES('Administrator','',?,?,'admin','active')");
$stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrator created.\n";
