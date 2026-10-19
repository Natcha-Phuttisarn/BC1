<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
logAdminAction($pdo, 'export', 'members', null, 'ส่งออกรายชื่อสมาชิกเป็น CSV');
$rows = $pdo->query("SELECT m.*, u.first_name, u.last_name, u.email, u.account_status FROM members m INNER JOIN users u ON u.id=m.user_id ORDER BY m.id DESC")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bizcomp-members-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['รหัสนิสิต','ชื่อ','นามสกุล','อีเมล','โทรศัพท์','คณะ','สาขา','ปีที่เข้าศึกษา','สถานะสมาชิก','สถานะบัญชี','วันที่เข้าร่วม']);
foreach ($rows as $row) {
    $status = getAutomaticMemberStatus($row);
    fputcsv($out, [$row['student_id'],$row['first_name'],$row['last_name'],$row['email'],formatPhoneNumber($row['phone'] ?? ''),$row['faculty'],$row['major'],getMemberAdmissionYear($row),$status['text'],$row['account_status'],$row['joined_at']]);
}
fclose($out); exit;
