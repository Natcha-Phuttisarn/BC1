<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'แก้ไขบัญชีผู้ใช้งาน';
$userId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT u.*, m.id AS member_id, m.student_id
    FROM users u
    LEFT JOIN members m ON m.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['user_admin_error'] = 'ไม่พบบัญชีผู้ใช้งาน';
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_user_csrf'])) {
    $_SESSION['admin_user_csrf'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['user_admin_error'] ?? '';
unset($_SESSION['user_admin_error']);

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel">
    <div class="panel-head">
        <div>
            <small>USER ACCOUNT</small>
            <h3>แก้ไขบัญชีผู้ใช้งาน</h3>
        </div>
        <a href="index.php" class="secondary-btn"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    </div>

    <?php if ($error): ?>
        <div class="form-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="update.php" method="POST" class="activity-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_user_csrf']) ?>">
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

        <div class="form-section">
            <div class="form-section-title">
                <div class="form-section-icon"><i class="fa-regular fa-user"></i></div>
                <div><h4>ข้อมูลบัญชี</h4><p>แก้ไขข้อมูลพื้นฐานและอีเมลสำหรับเข้าสู่ระบบ</p></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>ชื่อ <span>*</span></label><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required></div>
                <div class="form-group"><label>นามสกุล <span>*</span></label><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required></div>
                <div class="form-group full"><label>อีเมล <span>*</span></label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                <div class="form-group full"><label>ตั้งรหัสผ่านใหม่</label><input type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="เว้นว่างหากไม่ต้องการเปลี่ยน"><small class="form-help">อย่างน้อย 8 ตัวอักษร</small></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">
                <div class="form-section-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div><h4>สิทธิ์และสถานะ</h4><p>ควบคุมการเข้าถึงเว็บไซต์และระบบผู้ดูแล</p></div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>สิทธิ์ผู้ใช้งาน</label>
                    <select name="role">
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>ผู้ใช้งานทั่วไป</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>สถานะบัญชี</label>
                    <select name="account_status">
                        <option value="active" <?= $user['account_status'] === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
                        <option value="inactive" <?= $user['account_status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
                        <option value="blocked" <?= $user['account_status'] === 'blocked' ? 'selected' : '' ?>>บล็อกบัญชี</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($user['profile_image'])): ?>
                <label class="remove-user-photo"><input type="checkbox" name="remove_profile_image" value="1"> ลบรูปโปรไฟล์ปัจจุบันของผู้ใช้งาน</label>
            <?php endif; ?>

            <?php if (!empty($user['member_id'])): ?>
                <p class="member-edit-note"><i class="fa-solid fa-id-card"></i> บัญชีนี้เป็นสมาชิก รหัส <?= htmlspecialchars($user['student_id']) ?> — <a href="../members/edit.php?id=<?= (int)$user['member_id'] ?>">แก้ไขข้อมูลสมาชิก</a></p>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <a href="index.php" class="secondary-btn">ยกเลิก</a>
            <button type="submit" class="primary-btn"><i class="fa-solid fa-floppy-disk"></i> บันทึกการแก้ไข</button>
        </div>
    </form>
</section>

<style>
.remove-user-photo{display:inline-flex;align-items:center;gap:7px;margin-top:15px;color:#9a4650;font-size:10px;cursor:pointer}.member-edit-note{margin:17px 0 0;padding:12px;border-radius:9px;background:#eef5fd;color:#536f8f;font-size:10px}.member-edit-note a{color:#1768cf;font-weight:600}
</style>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
