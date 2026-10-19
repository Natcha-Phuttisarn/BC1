<?php
// CLI only. Uses a newly created disposable database, never BC_DB_NAME.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$host = getenv('BC_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('BC_TEST_DB_PORT') ?: '3306';
$user = getenv('BC_TEST_DB_USER') ?: 'root';
$pass = getenv('BC_TEST_DB_PASSWORD') ?: '';
$db = 'bc1_test_' . bin2hex(random_bytes(6));
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = null;
$createdFiles = [];
$log = tempnam(sys_get_temp_dir(), 'bc1-log-');
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo "PASS: $label\n";
}
function request($client, string $path, ?array $data = null): array {
    global $base;
    curl_setopt_array($client, [CURLOPT_URL => $base . '/' . $path, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => $data !== null]);
    if ($data !== null) curl_setopt($client, CURLOPT_POSTFIELDS, $data);
    $raw = curl_exec($client);
    if ($raw === false) throw new RuntimeException(curl_error($client));
    $status = curl_getinfo($client, CURLINFO_RESPONSE_CODE);
    $size = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    check($status < 500, "HTTP $status $path");
    return ['status' => $status, 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}
function token(array $response, string $name = 'csrf_token'): string {
    preg_match('/name="' . preg_quote($name, '/') . '"\s+value="([^"]+)"/', $response['body'], $m);
    if (!isset($m[1])) throw new RuntimeException('Missing form token: ' . $name);
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}
function client() { $c = curl_init(); curl_setopt($c, CURLOPT_COOKIEFILE, ''); return $c; }
try {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    $pdo->exec(file_get_contents($root . '/database/hosting_schema.sql'));
    check(count($pdo->query('SHOW TABLES')->fetchAll()) === 20, 'Fresh hosting schema: 20 tables');
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,role) VALUES('Test','Admin','admin@example.test',?,'admin')")
        ->execute([password_hash('Test-password-123', PASSWORD_DEFAULT)]);
    $environment = getenv();
    foreach (['BC_DB_HOST'=>$host,'BC_DB_PORT'=>$port,'BC_DB_NAME'=>$db,'BC_DB_USERNAME'=>$user,
        'BC_DB_PASSWORD'=>$pass,'BC_MAIL_USERNAME'=>'','BC_MAIL_PASSWORD'=>'','BC_OTP_HMAC_SECRET'=>bin2hex(random_bytes(32)),
        'BC_BASE_PATH'=>'__auto__','BC_SECURE_COOKIES'=>'0'] as $key=>$value) $environment[$key]=$value;
    // OS-selected free TCP port, bound only to loopback.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . sys_get_temp_dir(), '-d', 'upload_tmp_dir=' . sys_get_temp_dir(), '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=24M', '-S', $address, '-t', $root],
        [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']], $pipes, $root, $environment);
    for ($i=0;$i<40;$i++) { $ready=@fsockopen('127.0.0.1', (int)substr(strrchr($address, ':'),1)); if($ready){fclose($ready);break;} usleep(100000); }
    $guest = client(); $admin = client();
    foreach (['index.php','about.php','history.php','goals.php','team.php','contact.php','activities.php','gallery.php','privacy.php','user_guide.php','login.php','register.php','forgot_password_otp.php','verify_otp.php','reset_password_otp.php'] as $page) {
        $r = request($guest,$page);
        check(!str_contains($r['body'], '/BC1/'), 'Portable root links: ' . $page);
    }
    $r=request($guest,'admin/index.php');
    check($r['status']===302 && str_contains($r['headers'],'Location: /admin/login.php'), 'Anonymous admin access denied');
    request($admin,'admin/login.php',['email'=>'admin@example.test','password'=>'Test-password-123']);
    foreach (['index.php','activities/index.php','activities/create.php','gallery/index.php','gallery/form.php','registrations/index.php','members/index.php','users/index.php','membership/applications.php','payments/index.php','reports/members.php','team/index.php','team/form.php','settings/index.php','audit/index.php','backup/index.php','finance/index.php'] as $page) {
        $r=request($admin,'admin/'.$page); check($r['status']===200, 'Admin page renders: '.$page);
    }
    $r=request($admin,'admin/activities/create.php');
    request($admin,'admin/activities/save.php',['csrf_token'=>token($r),'title'=>'Hosting test','description'=>'Test description',
        'short_description'=>'Test','location'=>'Test room','activity_date'=>date('Y-m-d',strtotime('+3 days')),
        'start_time'=>'10:00','end_time'=>'12:00','registration_open'=>date('Y-m-d H:i:s',strtotime('-1 day')),
        'registration_close'=>date('Y-m-d H:i:s',strtotime('+2 days')),'max_participants'=>'5','action'=>'publish']);
    $activityId=(int)$pdo->query("SELECT id FROM activities WHERE title='Hosting test'")->fetchColumn();
    check($activityId>0,'Admin creates published activity');
    request($guest,'activities.php?id='.$activityId);
    request($admin,'admin/activities/edit.php?id='.$activityId);
    $attachmentName='hosting-test-'.bin2hex(random_bytes(8)).'.txt';
    $attachment=$root.'/uploads/activity_files/'.$attachmentName;
    file_put_contents($attachment,'hosting attachment'); $createdFiles[]=$attachment;
    $pdo->prepare('INSERT INTO activity_files(activity_id,original_name,stored_name,file_path,file_size,mime_type,file_extension) VALUES(?,?,?,?,?,?,?)')
        ->execute([$activityId,'test.txt',$attachmentName,'uploads/activity_files/'.$attachmentName,18,'text/plain','txt']);
    $attachmentId=(int)$pdo->lastInsertId();
    check(request(client(),'download_activity_file.php?id='.$attachmentId)['body']==='hosting attachment','Published attachment download');
    $pdo->exec("UPDATE activities SET publish_status='draft' WHERE id=$activityId");
    check(request(client(),'download_activity_file.php?id='.$attachmentId)['status']===404,'Draft attachment denied to guest');
    check(request($admin,'download_activity_file.php?id='.$attachmentId)['status']===200,'Draft attachment accessible to admin');
    $pdo->exec("UPDATE activities SET publish_status='published' WHERE id=$activityId");
    $pdo->prepare('UPDATE activity_files SET file_path=? WHERE id=?')->execute(['includes/db.php',$attachmentId]);
    check(request($admin,'download_activity_file.php?id='.$attachmentId)['status']===404,'Attachment cannot escape upload directory');
    $r=request($guest,'register.php');
    $studentId=substr((string)((int)date('Y')+543),-2).'123456';
    request($guest,'register_step1_save.php',['csrf_token'=>token($r),'first_name'=>'Test','last_name'=>'Member','student_id'=>$studentId,
        'faculty'=>'Test','major'=>'Test','gender'=>'','phone'=>'0812345678','email'=>'member@example.test',
        'password'=>'Test-password-123','confirm_password'=>'Test-password-123']);
    $r=request($guest,'register_payment.php');
    $png=tempnam(sys_get_temp_dir(),'bc1-png-'); $createdFiles[]=$png;
    file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    request($guest,'register_payment_save.php',['csrf_token'=>token($r),'submission_token'=>token($r,'submission_token'),
        'payment_method'=>'bank_transfer','confirm_information'=>'1','privacy_consent'=>'1','payment_slip'=>new CURLFile($png,'image/png','test.png')]);
    $memberId=(int)$pdo->query("SELECT id FROM users WHERE email='member@example.test'")->fetchColumn();
    check($memberId>0,'Two-step registration creates account');
    foreach($pdo->query('SELECT payment_slip FROM membership_payments')->fetchAll(PDO::FETCH_COLUMN) as $file) {
        $createdFiles[]=$root.'/uploads/payments/'.basename($file);
    }
    $applicationId=(int)$pdo->query("SELECT id FROM membership_applications WHERE user_id=$memberId")->fetchColumn();
    $r=request($admin,'admin/membership/applications.php');
    request($admin,'admin/membership/update_status.php',['csrf_token'=>token($r),'application_id'=>$applicationId,'action'=>'approve']);
    check($pdo->query("SELECT application_status FROM membership_applications WHERE id=$applicationId")->fetchColumn()==='approved','Membership approval');
    $member=client(); request($member,'login.php',['email'=>'member@example.test','password'=>'Test-password-123']);
    foreach(['profile.php','member_status.php','members.php','membership_apply.php','membership_payment.php','change_password.php'] as $page) request($member,$page);
    $paymentId=(int)$pdo->query('SELECT id FROM membership_payments LIMIT 1')->fetchColumn();
    check(request($member,'payment_slip_view.php?payment_id='.$paymentId)['status']===200,'Owner can read payment slip');
    check(request(client(),'payment_slip_view.php?payment_id='.$paymentId)['status']===401,'Anonymous slip access denied');
    $receiptId=(int)$pdo->query('SELECT id FROM membership_receipts LIMIT 1')->fetchColumn();
    check($receiptId>0,'Approval creates receipt');
    check(request($member,'receipt.php?id='.$receiptId)['status']===200,'Member receipt renders');
    request($member,'activity_register.php',['csrf_token'=>'invalid','activity_id'=>$activityId]);
    check((int)$pdo->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn()===0,'Invalid CSRF cannot register');
    $r=request($member,'activities.php?id='.$activityId);
    request($member,'activity_register.php',['csrf_token'=>token($r),'activity_id'=>$activityId]);
    check((int)$pdo->query("SELECT COUNT(*) FROM activity_registrations WHERE user_id=$memberId AND activity_id=$activityId")->fetchColumn()===1,'Member registers for activity');
    foreach(['index.php','attendance.php','report.php'] as $page) request($admin,'admin/registrations/'.$page.'?activity_id='.$activityId);
    $r=request($admin,'admin/registrations/attendance.php?activity_id='.$activityId);
    request($admin,'admin/registrations/attendance_save.php',['csrf_token'=>token($r),'activity_id'=>$activityId,
        'user_id[0]'=>$memberId,'status['.$memberId.']'=>'present','note['.$memberId.']'=>'Test']);
    check($pdo->query("SELECT attendance_status FROM activity_attendance WHERE user_id=$memberId")->fetchColumn()==='present','Attendance saves');
    $r=request($admin,'admin/gallery/form.php');
    request($admin,'admin/gallery/save.php',['csrf_token'=>token($r),'title'=>'Test gallery','description'=>'Test',
        'activity_id'=>$activityId,'activity_date'=>date('Y-m-d'),'status'=>'active','cover_image'=>new CURLFile($png,'image/png','test.png')]);
    $gallery=$pdo->query('SELECT * FROM activity_gallery LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check((bool)$gallery,'Gallery image upload saves');
    $createdFiles[]=$root.'/uploads/gallery/'.$gallery['cover_image'];
    check(request(client(),'gallery_detail.php?id='.$gallery['id'])['status']===200,'Published gallery renders');
    $r=request($admin,'admin/finance/index.php');
    request($admin,'admin/finance/income_save.php',['csrf_token'=>token($r),'income_date'=>date('Y-m-d'),'source'=>'Test','description'=>'Test income','amount'=>'12.50']);
    check((int)$pdo->query('SELECT COUNT(*) FROM finance_incomes')->fetchColumn()===1,'Finance income saves');
    $r=request($admin,'admin/backup/index.php');
    $backup=request($admin,'admin/backup/download.php',['csrf_token'=>token($r)]);
    check(str_contains($backup['body'],'CREATE TABLE') && str_contains($backup['body'],'password_reset_otp_challenges'),'Database backup includes OTP tables');
    require_once $root.'/includes/password_otp.php';
    $service=new PasswordOtpService(new PdoPasswordOtpRepository($pdo),str_repeat('t',64));
    $mail=new class implements PasswordOtpMailTransport { public string $code=''; public function send(string $email,string $name,string $code):bool {$this->code=$code;return true;} };
    check($service->request('member@example.test','127.0.0.1',$mail)['status']==='issued','OTP issue with fake transport');
    check($service->request('member@example.test','127.0.0.1',$mail)['status']==='cooldown','OTP resend cooldown');
    $verified=$service->verify('member@example.test','127.0.0.1',$mail->code);
    check($verified['status']==='verified','OTP verification');
    $reset=$service->reset($verified['capability'],'New-test-password-456');
    check($reset['status']==='reset','OTP password reset');
    check($service->reset($verified['capability'],'Other-password-456')['status']!=='reset','OTP token single use');
    check(password_verify('New-test-password-456',$pdo->query("SELECT password FROM users WHERE id=$memberId")->fetchColumn()),'Password changed in database');
    $fresh=client();
    request($fresh,'login.php',['email'=>'member@example.test','password'=>'New-test-password-456']);
    check(request($fresh,'profile.php')['status']===200,'Login with reset password');
    proc_terminate($server); proc_close($server); $server=null;
    $server=proc_open([PHP_BINARY,'-d','session.save_path='.sys_get_temp_dir(),'-S',$address,'-t',dirname($root)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,$environment);
    usleep(500000);
    $base.='/'.basename($root);
    $r=request(client(),'index.php'); check(str_contains($r['body'],'/'.basename($root).'/assets/css/style.css'),'Subdirectory assets');
    $r=request(client(),'admin/index.php'); check(str_contains($r['headers'],'Location: /'.basename($root).'/admin/login.php'),'Subdirectory redirects');
    proc_terminate($server); proc_close($server); $server=null;
    check(!preg_match('/PHP (Warning|Fatal error|Parse error|Deprecated|Notice)/',file_get_contents($log)),'No PHP runtime warnings or errors');
    echo "SUCCESS: $checks checks\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $pdo->exec("DROP DATABASE IF EXISTS `$db`");
    foreach($createdFiles as $file) if(is_file($file)) unlink($file);
    if(is_file($log)) { if(isset($reset)) unlink($log); else echo "Server log: $log\n"; }
}
