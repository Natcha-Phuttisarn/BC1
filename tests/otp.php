<?php
// CLI regression tests. All writes are confined to a randomly named test database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/password_otp.php';
$root = dirname(__DIR__);
$host = getenv('BC_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('BC_TEST_DB_PORT') ?: '3306';
$user = getenv('BC_TEST_DB_USER') ?: 'root';
$pass = getenv('BC_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'bc1_otp_test_' . bin2hex(random_bytes(8));
$server = null;
$created = false;
$log = tempnam(sys_get_temp_dir(), 'bc1-otp-');
$checks = 0;
function checkOtp(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    ++$checks;
    echo "PASS: $label\n";
}
function otpHttp($client, string $path, ?array $data = null): array {
    global $base;
    curl_setopt_array($client, [CURLOPT_URL => "$base/$path", CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
        CURLOPT_PROXY => '', CURLOPT_POST => $data !== null]);
    if ($data !== null) { curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($client);
    if ($raw === false) { throw new RuntimeException(curl_error($client)); }
    $size = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}
function otpToken(array $response): string {
    preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $m);
    return $m[1] ?? '';
}
try {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo->exec("USE `$db`");
    $pdo->exec(file_get_contents($root . '/database/hosting_schema.sql'));
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,role,account_status) VALUES('OTP','Test','otp@example.test',?,'user','active')")
        ->execute([password_hash('Original-password-123', PASSWORD_DEFAULT)]);
    $secret = bin2hex(random_bytes(32));
    $service = new PasswordOtpService(new PdoPasswordOtpRepository($pdo), $secret);
    $mail = new class implements PasswordOtpMailTransport {
        public string $code = '';
        public int $sent = 0;
        public bool $success = true;
        public function send(string $email, string $name, string $code): bool {
            $this->code = $code; ++$this->sent; return $this->success;
        }
    };
    $email = 'otp@example.test'; $ip = '127.0.0.1'; $now = time();
    $r = $service->request($email, $ip, $mail, $now);
    checkOtp($r['status'] === 'issued' && preg_match('/^[0-9]{6}$/D', $mail->code), 'Issue six-digit OTP');
    $first = $mail->code;
    $r = $service->request($email, $ip, $mail, $now + 10);
    checkOtp($r['status'] === 'cooldown' && $r['expires_at'] === $now + 300 && $r['resend_at'] === $now + 60 && $mail->sent === 1, 'Cooldown preserves deadlines and does not send again');
    checkOtp($service->verify($email, $ip, 'invalid', $now + 11)['status'] === 'invalid', 'Reject malformed code');
    checkOtp($service->request($email, $ip, $mail, $now + 61)['status'] === 'issued', 'Resend after cooldown');
    // Generate another code in the extremely unlikely event random codes match.
    if ($first === $mail->code) { $service->request($email, $ip, $mail, $now + 122); }
    checkOtp($service->verify($email, $ip, $first, $now + 123)['status'] === 'invalid', 'Old code invalidated by resend');
    $v = $service->verify($email, $ip, $mail->code, $now + 124);
    checkOtp($v['status'] === 'verified', 'Verify current OTP');
    checkOtp($service->verify($email, $ip, $mail->code, $now + 125)['status'] === 'invalid', 'OTP cannot be reused');
    checkOtp($service->reset($v['capability'], 'Original-password-123', $now + 126)['status'] === 'same_password', 'Reject same password');
    checkOtp($service->reset($v['capability'], 'New-password-456', $now + 127)['status'] === 'reset', 'Retry with different password succeeds');
    checkOtp(password_verify('New-password-456', $pdo->query('SELECT password FROM users LIMIT 1')->fetchColumn()), 'New password hash saved');
    checkOtp($service->reset($v['capability'], 'Another-password-789', $now + 128)['status'] === 'invalid', 'Reset capability is single use');
    $service->request($email, $ip, $mail, $now + 200);
    checkOtp($service->verify($email, $ip, $mail->code, $now + 500)['status'] === 'expired', 'OTP expires exactly at five minutes');
    $service->request($email, $ip, $mail, $now + 501);
    for ($i = 0; $i < 5; ++$i) { $r = $service->verify($email, $ip, 'invalid', $now + 502); }
    checkOtp($r['status'] === 'locked', 'Five wrong attempts lock the challenge');
    checkOtp($service->verify($email, $ip, $mail->code, $now + 503)['status'] === 'invalid', 'Correct code rejected after lock');
    $mail->success = false;
    checkOtp($service->request($email, $ip, $mail, $now + 3601)['status'] === 'mail_failed', 'Delivery failure detected');
    checkOtp($service->verify($email, $ip, $mail->code, $now + 3602)['status'] === 'invalid', 'Undelivered code invalidated');
    $mail->success = true;
    $service->request($email, $ip, $mail, $now + 3662);
    $v = $service->verify($email, $ip, $mail->code, $now + 3663);
    checkOtp($service->reset($v['capability'], 'Expired-password-789', $now + 4263)['status'] === 'invalid', 'Reset capability expires at ten minutes');
    for ($i = 0; $i < 3; ++$i) { $service->request($email, $ip, $mail, $now + 4324 + $i * 61); }
    checkOtp($service->request($email, $ip, $mail, $now + 4507)['status'] === 'rate_limited', 'Hourly request limit enforced');
    $sent = $mail->sent;
    checkOtp($service->request('missing@example.test', $ip, $mail, $now)['status'] === 'generic' && $mail->sent === $sent, 'Unknown account gets generic response without mail');

    $environment = getenv();
    foreach (['BC_DB_HOST'=>$host,'BC_DB_PORT'=>$port,'BC_DB_NAME'=>$db,'BC_DB_USERNAME'=>$user,'BC_DB_PASSWORD'=>$pass,
        'BC_OTP_HMAC_SECRET'=>$secret,'BC_BASE_PATH'=>'','BC_SECURE_COOKIES'=>'0',
        'BC_MAIL_HOST'=>'127.0.0.1','BC_MAIL_PORT'=>'1','BC_MAIL_USERNAME'=>'test','BC_MAIL_PASSWORD'=>'test'] as $key=>$value) {
        $environment[$key] = $value;
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://' . $address;
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . sys_get_temp_dir(), '-S', $address, '-t', $root],
        [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']], $pipes, $root, $environment);
    for ($i=0; $i<40; ++$i) { $ready=@fsockopen('127.0.0.1', (int)substr(strrchr($address, ':'), 1)); if($ready){fclose($ready);break;} usleep(100000); }
    $client = curl_init(); curl_setopt($client, CURLOPT_COOKIEFILE, '');
    $r = otpHttp($client, 'verify_otp.php');
    checkOtp($r['status'] === 303 && str_contains($r['headers'], 'Location: forgot_password_otp.php'), 'Direct verification requires request session');
    $r = otpHttp($client, 'forgot_password_otp.php'); $token = otpToken($r);
    checkOtp($token !== '', 'Request form contains CSRF token');
    $r = otpHttp($client, 'forgot_password_otp.php', ['email'=>'http@example.test','csrf_token'=>'bad']);
    checkOtp($r['status'] === 200 && !str_contains($r['headers'], 'Location:'), 'Invalid CSRF rejected');
    $r = otpHttp($client, 'forgot_password_otp.php', ['email'=>'invalid','csrf_token'=>$token]);
    checkOtp($r['status'] === 200 && !str_contains($r['headers'], 'Location:'), 'Invalid email rejected');
    $r = otpHttp($client, 'forgot_password_otp.php', ['email'=>'http@example.test','csrf_token'=>$token]);
    checkOtp($r['status'] === 303 && str_contains($r['headers'], 'Location: verify_otp.php'), 'Accepted request redirects to OTP form');
    $r = otpHttp($client, 'verify_otp.php');
    checkOtp($r['status'] === 200 && str_contains($r['body'], 'name="otp"'), 'OTP form renders after redirect');
    $r = otpHttp($client, 'forgot_password_otp.php', ['email'=>'http@example.test','csrf_token'=>$token]);
    checkOtp($r['status'] === 303, 'Cooldown returns to verification');
    $r = otpHttp($client, 'reset_password_otp.php');
    checkOtp(!str_contains($r['body'], 'name="new_password"'), 'Cannot reset without verification');
    // Seed only the disposable database, using the same session email and service secret.
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password) VALUES('HTTP','Test','http@example.test',?)")
        ->execute([password_hash('Http-original-123', PASSWORD_DEFAULT)]);
    $pdo->exec('DELETE FROM password_reset_otp_limits');
    $service->request('http@example.test', $ip, $mail);
    $r = otpHttp($client, 'verify_otp.php', ['csrf_token'=>$token,'otp'=>$mail->code]);
    checkOtp($r['status'] === 303 && str_contains($r['headers'], 'Location: reset_password_otp.php'), 'Verified browser session redirects to reset');
    $r = otpHttp($client, 'reset_password_otp.php', ['csrf_token'=>$token,'new_password'=>'Http-original-123','confirm_password'=>'Http-original-123']);
    checkOtp(str_contains($r['body'], 'name="new_password"'), 'Same password retains reset form and authorization');
    $r = otpHttp($client, 'reset_password_otp.php', ['csrf_token'=>$token,'new_password'=>'Http-new-456','confirm_password'=>'Http-new-456']);
    checkOtp($r['status'] === 303 && str_contains($r['headers'], 'Location: login.php'), 'Successful reset redirects to login');
    checkOtp(password_verify('Http-new-456', $pdo->query("SELECT password FROM users WHERE email='http@example.test'")->fetchColumn()), 'Browser reset updates password');
    $r = otpHttp($client, 'reset_password_otp.php');
    checkOtp(!str_contains($r['body'], 'name="new_password"'), 'Successful reset clears browser capability');
    curl_close($client);
    checkOtp(!preg_match('/PHP (Warning|Fatal error|Parse error|Notice)/', file_get_contents($log)), 'No PHP runtime errors');
    echo "SUCCESS: $checks OTP checks; production data unchanged; real SMTP delivery not tested.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($created) { $pdo->exec("DROP DATABASE `$db`"); }
    if (is_file($log)) { unlink($log); }
}
