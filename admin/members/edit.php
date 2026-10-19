<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminMemberCsrf = $_SESSION['admin_member_csrf'] ?? '';

if (
    !is_string($adminMemberCsrf) ||
    preg_match('/\A[a-f0-9]{64}\z/D', $adminMemberCsrf) !== 1
) {
    $adminMemberCsrf = bin2hex(random_bytes(32));
    $_SESSION['admin_member_csrf'] = $adminMemberCsrf;
}

$adminPageTitle = 'แก้ไขข้อมูลสมาชิก';
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($memberId <= 0) {
    $_SESSION['member_error'] = 'ไม่พบข้อมูลสมาชิก';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.email, u.account_status
    FROM members m
    INNER JOIN users u ON u.id = m.user_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['member_error'] = 'ไม่พบข้อมูลสมาชิก';
    header('Location: index.php');
    exit;
}

$automatic = getAutomaticMemberStatus($member);
$admissionYear = getMemberAdmissionYear($member);
$currentYearLevel = $automatic['year_level'];

$error = $_SESSION['member_error'] ?? '';
unset($_SESSION['member_error']);

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel">

    <div class="panel-head">
        <div>
            <small>MEMBERS</small>
            <h3>แก้ไขข้อมูลสมาชิก</h3>
        </div>

        <a href="index.php" class="secondary-btn">
            <i class="fa-solid fa-arrow-left"></i>
            กลับ
        </a>
    </div>

    <?php if ($error): ?>
        <div class="form-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="update.php" method="POST" class="activity-form">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminMemberCsrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int)$member['user_id'] ?>">

        <!-- ข้อมูลส่วนตัว -->
        <div class="form-section">

            <div class="form-section-title">
                <div class="form-section-icon">
                    <i class="fa-regular fa-user"></i>
                </div>
                <div>
                    <h4>ข้อมูลส่วนตัว</h4>
                    <p>ข้อมูลบัญชีและข้อมูลพื้นฐานของสมาชิก</p>
                </div>
            </div>

            <div class="form-grid">

                <div class="form-group">
                    <label>ชื่อ <span>*</span></label>
                    <input
                        type="text"
                        name="first_name"
                        value="<?= htmlspecialchars($member['first_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>นามสกุล <span>*</span></label>
                    <input
                        type="text"
                        name="last_name"
                        value="<?= htmlspecialchars($member['last_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>อีเมล <span>*</span></label>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($member['email']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars($member['phone'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>เพศ</label>
                    <select name="gender">
                        <option value="">ไม่ระบุ</option>
                        <option value="ชาย" <?= ($member['gender'] ?? '') === 'ชาย' ? 'selected' : '' ?>>ชาย</option>
                        <option value="หญิง" <?= ($member['gender'] ?? '') === 'หญิง' ? 'selected' : '' ?>>หญิง</option>
                        <option value="อื่นๆ" <?= ($member['gender'] ?? '') === 'อื่นๆ' ? 'selected' : '' ?>>อื่น ๆ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>สถานะบัญชี</label>
                    <select name="account_status">
                        <option value="active" <?= $member['account_status'] === 'active' ? 'selected' : '' ?>>
                            ใช้งาน
                        </option>
                        <option value="inactive" <?= $member['account_status'] === 'inactive' ? 'selected' : '' ?>>
                            ปิดใช้งาน
                        </option>
                        <option value="blocked" <?= $member['account_status'] === 'blocked' ? 'selected' : '' ?>>
                            บล็อกบัญชี
                        </option>
                    </select>
                </div>

            </div>

        </div>

        <!-- ข้อมูลการศึกษา -->
        <div class="form-section">

            <div class="form-section-title">
                <div class="form-section-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <h4>ข้อมูลการศึกษา</h4>
                    <p>ชั้นปีจะคำนวณอัตโนมัติทุกวันที่ 1 มิถุนายน</p>
                </div>
            </div>

            <div class="form-grid">

                <div class="form-group">
                    <label>รหัสนิสิต <span>*</span></label>
                    <input
                        type="text"
                        name="student_id"
                        value="<?= htmlspecialchars($member['student_id']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>ปีที่เข้าเรียน พ.ศ. <span>*</span></label>
                    <input
                        type="number"
                        name="admission_year"
                        min="2500"
                        max="2700"
                        value="<?= htmlspecialchars($admissionYear ?? '') ?>"
                        placeholder="เช่น 2569"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>คณะ</label>
                    <input
                        type="text"
                        name="faculty"
                        value="<?= htmlspecialchars($member['faculty'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>สาขา</label>
                    <input
                        type="text"
                        name="major"
                        value="<?= htmlspecialchars($member['major'] ?? '') ?>"
                    >
                </div>

            </div>

            <!-- ชั้นปีอัตโนมัติ -->
            <div class="form-group full">
                <label>ชั้นปีปัจจุบันที่ระบบคำนวณ</label>

                <div style="padding:13px 15px;background:#edf4ff;border:1px solid #d8e7fa;border-radius:9px;color:#23558f;font-size:12px;">

                    <?php if ($automatic['code'] === 'graduated'): ?>

                        <i class="fa-solid fa-user-graduate"></i>
                        พ้นสภาพสมาชิก (เกินระยะเวลา 4 ปี)

                    <?php elseif ($currentYearLevel !== null && $currentYearLevel >= 1 && $currentYearLevel <= 4): ?>

                        <i class="fa-solid fa-graduation-cap"></i>
                        ชั้นปีที่ <?= (int)$currentYearLevel ?>

                    <?php else: ?>

                        <i class="fa-solid fa-circle-exclamation"></i>
                        ไม่สามารถคำนวณชั้นปีได้

                    <?php endif; ?>

                </div>

                <small class="form-help">
                    ไม่ต้องแก้ชั้นปีเอง ระบบจะเลื่อนปีให้อัตโนมัติ
                </small>
            </div>

        </div>

        <!-- สถานะสมาชิก -->
        <div class="form-section">

            <div class="form-section-title">
                <div class="form-section-icon">
                    <i class="fa-regular fa-id-card"></i>
                </div>
                <div>
                    <h4>สถานะสมาชิก</h4>
                    <p>แอดมินสามารถระงับหรือปิดใช้งานสมาชิกได้</p>
                </div>
            </div>

            <div class="form-grid">

                <div class="form-group">
                    <label>สถานะสมาชิก</label>

                    <select name="member_status">

                        <option value="active" <?= $member['member_status'] === 'active' ? 'selected' : '' ?>>
                            สมาชิกปกติ
                        </option>

                        <option value="inactive" <?= $member['member_status'] === 'inactive' ? 'selected' : '' ?>>
                            ไม่ใช้งาน
                        </option>

                        <option value="suspended" <?= $member['member_status'] === 'suspended' ? 'selected' : '' ?>>
                            ระงับสมาชิก
                        </option>

                    </select>

                    <small class="form-help">
                        “พ้นสภาพสมาชิก” ระบบจะคำนวณอัตโนมัติเมื่อเข้าสู่ปีที่ 5
                    </small>
                </div>

                <div class="form-group">
                    <label>วันที่เข้าร่วมชมรม</label>

                    <input
                        type="date"
                        name="joined_at"
                        value="<?= !empty($member['joined_at']) ? date('Y-m-d', strtotime($member['joined_at'])) : '' ?>"
                    >
                </div>

            </div>

        </div>

        <!-- ปุ่ม -->
        <div class="form-actions">

            <a href="index.php" class="secondary-btn">
                ยกเลิก
            </a>

            <button type="submit" class="primary-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                บันทึกการแก้ไข
            </button>

        </div>

    </form>

</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
