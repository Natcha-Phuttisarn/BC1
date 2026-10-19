<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// admin/index.php
// หน้านี้ใช้สำหรับแสดงแดชบอร์ดภาพรวมของผู้ดูแลระบบ
require_once __DIR__ . '/includes/admin_auth.php';
$adminPageTitle = 'แดชบอร์ด';

$memberCount = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE member_status='active'")->fetchColumn();
$activityCount = (int)$pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$applicationCount = (int)$pdo->query("SELECT COUNT(*) FROM membership_applications WHERE application_status IN ('pending','waiting_payment','checking_payment')")->fetchColumn();
$paymentCount = (int)$pdo->query("SELECT COUNT(*) FROM membership_payments WHERE payment_status='pending'")->fetchColumn();

$stmt = $pdo->query("SELECT id,title,activity_date,publish_status FROM activities ORDER BY created_at DESC LIMIT 5");
$latestActivities = $stmt->fetchAll();

include __DIR__ . '/includes/admin_header.php';
include __DIR__ . '/includes/admin_sidebar.php';
?>

<section class="welcome-card">
<div>
<span class="welcome-label">BIZCOMP CLUB ADMIN</span>
<h2>ยินดีต้อนรับ <?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?> 👋</h2>
<p>จัดการสมาชิก กิจกรรม และข้อมูลต่าง ๆ ของชมรมบิตคอมได้จากระบบนี้</p>
</div>
<div class="welcome-logo"><img src="<?= BC_BASE_PATH ?>/assets/images/logo.png" alt="Bizcomp Club"></div>
</section>

<div class="stat-grid">
<div class="stat-card"><div class="stat-top"><div class="stat-icon blue"><i class="fa-solid fa-users"></i></div><span>สมาชิก</span></div><strong><?= $memberCount ?></strong><p>สมาชิกปัจจุบัน</p></div>
<div class="stat-card"><div class="stat-top"><div class="stat-icon green"><i class="fa-regular fa-calendar-check"></i></div><span>กิจกรรม</span></div><strong><?= $activityCount ?></strong><p>กิจกรรมในระบบ</p></div>
<div class="stat-card"><div class="stat-top"><div class="stat-icon yellow"><i class="fa-solid fa-user-clock"></i></div><span>รอตรวจ</span></div><strong><?= $applicationCount ?></strong><p>คำขอสมาชิก</p></div>
<div class="stat-card"><div class="stat-top"><div class="stat-icon purple"><i class="fa-solid fa-wallet"></i></div><span>ชำระเงิน</span></div><strong><?= $paymentCount ?></strong><p>รายการรอตรวจสอบ</p></div>
</div>

<div class="dashboard-grid">
<section class="panel">
<div class="panel-head">
<div><small>ACTIVITIES</small><h3>กิจกรรมล่าสุด</h3></div>
<a href="activities/create.php" class="primary-btn"><i class="fa-solid fa-plus"></i> เพิ่มกิจกรรม</a>
</div>

<?php if ($latestActivities): ?>
<div class="table-wrap">
<table>
<thead><tr><th>กิจกรรม</th><th>วันที่จัด</th><th>สถานะ</th></tr></thead>
<tbody>
<?php foreach ($latestActivities as $activity): ?>
<tr>
<td><strong><?= htmlspecialchars($activity['title']) ?></strong></td>
<td><?= date('d/m/Y',strtotime($activity['activity_date'])) ?></td>
<td>
<?php if ($activity['publish_status']==='published'): ?>
<span class="badge published">เผยแพร่</span>
<?php elseif ($activity['publish_status']==='draft'): ?>
<span class="badge draft">ฉบับร่าง</span>
<?php else: ?>
<span class="badge hidden">ซ่อน</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty"><i class="fa-regular fa-calendar"></i><h3>ยังไม่มีกิจกรรม</h3><p>เริ่มต้นสร้างกิจกรรมแรกของชมรม</p></div>
<?php endif; ?>
</section>

<section class="panel">
<div class="panel-head"><div><small>QUICK MENU</small><h3>เมนูด่วน</h3></div></div>
<div class="quick-menu">
<a href="activities/create.php"><i class="fa-solid fa-plus"></i><div><strong>เพิ่มกิจกรรม</strong><span>สร้างกิจกรรมใหม่</span></div><i class="fa-solid fa-chevron-right"></i></a>
<a href="activities/index.php"><i class="fa-regular fa-calendar"></i><div><strong>จัดการกิจกรรม</strong><span>ดูรายการกิจกรรม</span></div><i class="fa-solid fa-chevron-right"></i></a>
<a href="../index.php" target="_blank"><i class="fa-solid fa-globe"></i><div><strong>ดูหน้าเว็บไซต์</strong><span>เปิดเว็บไซต์ผู้ใช้งาน</span></div><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
</div>
</section>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
