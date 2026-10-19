<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
logAdminAction($pdo, 'export', 'activities', null, 'ส่งออกข้อมูลกิจกรรมเป็น CSV');
$rows = $pdo->query("SELECT a.*,
 (SELECT COUNT(*) FROM activity_registrations ar WHERE ar.activity_id=a.id AND ar.registration_status='registered') registered_count,
 (SELECT COUNT(*) FROM activity_registrations ar WHERE ar.activity_id=a.id AND ar.registration_status='cancelled') cancelled_count
 FROM activities a ORDER BY a.activity_date DESC")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bizcomp-activities-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['รหัส','ชื่อกิจกรรม','วันที่จัด','เวลาเริ่ม','เวลาสิ้นสุด','สถานที่','จำนวนสูงสุด','สมัครอยู่','ยกเลิก','การเผยแพร่']);
foreach ($rows as $row) fputcsv($out, [$row['id'],$row['title'],$row['activity_date'],$row['start_time'],$row['end_time'],$row['location'],$row['max_participants'],$row['registered_count'],$row['cancelled_count'],$row['publish_status']]);
fclose($out); exit;
