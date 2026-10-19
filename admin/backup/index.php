<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$adminPageTitle = 'สำรองฐานข้อมูล';
if (empty($_SESSION['backup_csrf'])) $_SESSION['backup_csrf'] = bin2hex(random_bytes(32));
include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>
<section class="panel full-panel">
    <div class="panel-head"><div><small>DATABASE BACKUP</small><h3>สำรองฐานข้อมูล</h3></div></div>
    <div style="max-width:720px;padding:22px;border:1px solid #e7edf5;border-radius:14px;background:#f8fafc;">
        <h3 style="margin-bottom:8px;color:#233f64;"><i class="fa-solid fa-database"></i> ดาวน์โหลดไฟล์ SQL</h3>
        <p style="line-height:1.8;color:#64748b;">ไฟล์สำรองประกอบด้วยโครงสร้างตารางและข้อมูลทั้งหมด ควรเก็บไว้ในพื้นที่ปลอดภัยและจำกัดเฉพาะผู้ดูแลระบบ</p>
        <form method="POST" action="download.php" style="margin-top:18px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['backup_csrf']) ?>">
            <button class="primary-btn" type="submit"><i class="fa-solid fa-download"></i> ดาวน์โหลดฐานข้อมูลสำรอง</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
