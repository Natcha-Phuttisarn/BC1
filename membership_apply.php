<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


/* =========================================
   ต้อง Login ก่อน
========================================= */

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$membershipApplyCsrf = $_SESSION['membership_apply_csrf'] ?? '';

if (
    !is_string($membershipApplyCsrf) ||
    preg_match('/\A[a-f0-9]{64}\z/D', $membershipApplyCsrf) !== 1
) {
    $membershipApplyCsrf = bin2hex(random_bytes(32));
    $_SESSION['membership_apply_csrf'] = $membershipApplyCsrf;
}

$userId = (int)$_SESSION['user_id'];


/* =========================================
   ถ้าเป็นสมาชิกอยู่แล้ว
========================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

if ($stmt->fetch()) {
    header('Location: member_status.php');
    exit;
}


/* =========================================
   ตรวจคำขอที่ยังดำเนินการอยู่
========================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM membership_applications
    WHERE user_id = ?
    AND application_status IN (
        'pending',
        'waiting_payment',
        'checking_payment',
        'approved'
    )
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$userId]);

$existingApplication = $stmt->fetch();

if ($existingApplication) {
    header('Location: member_status.php');
    exit;
}


$error = $_SESSION['membership_error'] ?? '';
$old = $_SESSION['membership_old'] ?? [];

unset($_SESSION['membership_error']);
unset($_SESSION['membership_old']);


$pageTitle = 'สมัครสมาชิกชมรม';
$pageCss = 'membership.css';

include 'includes/header.php';

?>

<section class="membership-page">

    <div class="membership-container">

        <div class="membership-card">

            <div class="membership-heading">

                <div class="membership-heading-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <div>

                    <span>
                        BIZCOMP CLUB MEMBERSHIP
                    </span>

                    <h1>
                        สมัครสมาชิกชมรม
                    </h1>

                    <p>
                        กรอกข้อมูลนิสิตเพื่อส่งคำขอสมัครสมาชิก
                        Bizcomp Club
                    </p>

                </div>

            </div>


            <?php if ($error): ?>

                <div class="membership-alert error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <div class="membership-info">

                <i class="fa-solid fa-circle-info"></i>

                <div>
                    <strong>
                        ระบบคำนวณชั้นปีให้อัตโนมัติ
                    </strong>

                    <p>
                        ระบบจะตรวจปีที่เข้าเรียนจากเลข 2 ตัวแรกของรหัสนิสิต
                        และคำนวณชั้นปีตามปีการศึกษาปัจจุบัน
                        โดยเปลี่ยนชั้นปีทุกวันที่ 1 มิถุนายน
                    </p>
                </div>

            </div>


            <form
                action="membership_apply_save.php"
                method="POST"
                class="membership-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($membershipApplyCsrf, ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="membership-form-section">

                    <div class="section-title">

                        <i class="fa-solid fa-graduation-cap"></i>

                        <div>
                            <h3>ข้อมูลการศึกษา</h3>
                            <p>ข้อมูลสำหรับสมัครสมาชิกชมรม</p>
                        </div>

                    </div>


                    <div class="membership-form-grid">


                        <div class="membership-form-group">

                            <label>
                                รหัสนิสิต
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="student_id"
                                value="<?= htmlspecialchars($old['student_id'] ?? '') ?>"
                                placeholder="กรอกรหัสนิสิต"
                                maxlength="30"
                                required
                            >

                            <small>
                                ตัวอย่าง รหัสขึ้นต้น 69 = เข้าเรียนปี 2569
                            </small>

                        </div>


                        <div class="membership-form-group">

                            <label>
                                คณะ
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="faculty"
                                value="<?= htmlspecialchars(
                                    $old['faculty']
                                    ?? 'คณะการบัญชีและการจัดการ'
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="membership-form-group">

                            <label>
                                สาขา
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="major"
                                value="<?= htmlspecialchars(
                                    $old['major']
                                    ?? 'คอมพิวเตอร์ธุรกิจ'
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="membership-form-group">

                            <label>
                                เพศ
                            </label>

                            <select name="gender">

                                <option value="">
                                    ไม่ระบุ
                                </option>

                                <option
                                    value="ชาย"
                                    <?= ($old['gender'] ?? '') === 'ชาย'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    ชาย
                                </option>

                                <option
                                    value="หญิง"
                                    <?= ($old['gender'] ?? '') === 'หญิง'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    หญิง
                                </option>

                                <option
                                    value="อื่นๆ"
                                    <?= ($old['gender'] ?? '') === 'อื่นๆ'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    อื่น ๆ
                                </option>

                            </select>

                        </div>


                        <div class="membership-form-group full">

                            <label>
                                เบอร์โทรศัพท์
                            </label>

                            <input
                                type="text"
                                name="phone"
                                value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                                placeholder="กรอกเบอร์โทรศัพท์"
                                maxlength="30"
                            >

                        </div>

                    </div>

                </div>


                <div class="membership-confirm">

                    <i class="fa-regular fa-circle-check"></i>

                    <p>
                        หลังจากส่งคำขอแล้ว ผู้ดูแลระบบจะตรวจสอบข้อมูล
                        หากได้รับอนุมัติจึงสามารถดำเนินการชำระค่าสมาชิกได้
                    </p>

                </div>

                <label class="membership-confirm" style="cursor:pointer;">
                    <input type="checkbox" name="privacy_consent" value="1" required style="margin-right:8px;">
                    <span>ยอมรับ <a href="privacy.php" target="_blank" rel="noopener">นโยบายความเป็นส่วนตัว</a> เพื่อให้ชมรมใช้ข้อมูลสำหรับการสมัครสมาชิก</span>
                </label>


                <div class="membership-actions">

                    <a
                        href="member_status.php"
                        class="membership-secondary-btn"
                    >
                        ยกเลิก
                    </a>

                    <button
                        type="submit"
                        class="membership-primary-btn"
                    >
                        <i class="fa-regular fa-paper-plane"></i>
                        ส่งคำขอสมัครสมาชิก
                    </button>

                </div>

            </form>

        </div>

    </div>

</section>

<?php include 'includes/footer.php'; ?>
