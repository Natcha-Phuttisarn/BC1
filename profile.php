<?php
require_once __DIR__ . '/includes/bootstrap.php';
// profile.php
// หน้านี้ใช้สำหรับแสดงและแก้ไขข้อมูลส่วนตัวของผู้ใช้งาน

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


// =========================================
// ต้องเข้าสู่ระบบก่อน
// =========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$error = '';
$success = '';

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$profileCsrf = $_SESSION['profile_csrf'];


// =========================================
// บันทึกการแก้ไขข้อมูล
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $normalizedPhone = normalizePhoneNumber($phone);
    $csrfToken = $_POST['csrf_token'] ?? '';
    $removeProfileImage = isset($_POST['remove_profile_image']);
    $newProfileImage = null;
    $oldProfileImage = null;

    if (
        $csrfToken === '' ||
        !hash_equals($profileCsrf, $csrfToken)
    ) {

        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';

    } elseif (
        $firstName === '' ||
        $lastName === '' ||
        $email === ''
    ) {

        $error = 'กรุณากรอกชื่อ นามสกุล และอีเมลให้ครบ';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'รูปแบบอีเมลไม่ถูกต้อง';

    } elseif ($normalizedPhone === false) {

        $error = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก';

    } else {

        $phone = $normalizedPhone ?? '';

        // ตรวจสอบอีเมลซ้ำและอ่านชื่อรูปเดิม
        $check = $pdo->prepare("
            SELECT id, profile_image
            FROM users
            WHERE (email = ? AND id != ?)
               OR id = ?
        ");

        $check->execute([
            $email,
            $userId,
            $userId
        ]);

        $emailDuplicated = false;

        foreach ($check->fetchAll() as $checkedUser) {
            if ((int)$checkedUser['id'] === $userId) {
                $oldProfileImage = $checkedUser['profile_image'] ?? null;
            } else {
                $emailDuplicated = true;
            }
        }

        if ($emailDuplicated) {

            $error = 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว';

        } else {

            // ตรวจสอบไฟล์รูปโปรไฟล์ก่อนบันทึก
            $profileFile = $_FILES['profile_image'] ?? null;

            if (
                $profileFile &&
                ($profileFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                if (($profileFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $error = 'อัปโหลดรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
                } elseif (($profileFile['size'] ?? 0) > 5 * 1024 * 1024) {
                    $error = 'รูปโปรไฟล์ต้องมีขนาดไม่เกิน 5 MB';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($profileFile['tmp_name']);
                    $allowedImages = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowedImages[$mimeType]) || @getimagesize($profileFile['tmp_name']) === false) {
                        $error = 'รองรับเฉพาะรูป JPG, PNG หรือ WEBP เท่านั้น';
                    } else {
                        $profileDirectory = __DIR__ . '/uploads/profiles';

                        if (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0775, true) && !is_dir($profileDirectory)) {
                            $error = 'ไม่สามารถสร้างพื้นที่จัดเก็บรูปโปรไฟล์ได้';
                        } else {
                            $newProfileImage = 'profile_' . $userId . '_' . bin2hex(random_bytes(12)) . '.' . $allowedImages[$mimeType];

                            if (!move_uploaded_file($profileFile['tmp_name'], $profileDirectory . '/' . $newProfileImage)) {
                                $newProfileImage = null;
                                $error = 'ไม่สามารถบันทึกรูปโปรไฟล์ได้';
                            }
                        }
                    }
                }
            }

            if ($error !== '') {
                // หยุดก่อนอัปเดตข้อมูลเมื่อไฟล์ไม่ถูกต้อง
            } else {

            try {

                $pdo->beginTransaction();


                // อัปเดตข้อมูลบัญชี
                $profileImageToSave = $newProfileImage !== null
                    ? $newProfileImage
                    : ($removeProfileImage ? null : $oldProfileImage);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        profile_image = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $profileImageToSave,
                    $userId
                ]);


                // อัปเดตเบอร์โทรเฉพาะเมื่อฟอร์มมีช่องนี้
                if (array_key_exists('phone', $_POST)) {
                    $stmt = $pdo->prepare("
                        UPDATE members
                        SET phone = ?
                        WHERE user_id = ?
                    ");

                    $stmt->execute([
                        $phone !== '' ? $phone : null,
                        $userId
                    ]);
                }


                $pdo->commit();


                // อัปเดต Session
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $email;
                $_SESSION['profile_image'] = $profileImageToSave;

                $success = 'บันทึกข้อมูลเรียบร้อยแล้ว';

                if (
                    ($newProfileImage !== null || $removeProfileImage) &&
                    !empty($oldProfileImage) &&
                    basename($oldProfileImage) === $oldProfileImage
                ) {
                    $oldFile = __DIR__ . '/uploads/profiles/' . $oldProfileImage;
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($newProfileImage !== null) {
                    $newFile = __DIR__ . '/uploads/profiles/' . $newProfileImage;
                    if (is_file($newFile)) {
                        @unlink($newFile);
                    }
                }

                $error = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
            }
            }
        }
    }
}


// =========================================
// ดึงข้อมูลผู้ใช้
// =========================================

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.profile_image,
        u.role,
        u.account_status,
        u.created_at,

        m.student_id,
        m.admission_year,
        m.faculty,
        m.major,
        m.gender,
        m.phone,
        m.member_status

    FROM users u

    LEFT JOIN members m
        ON m.user_id = u.id

    WHERE u.id = ?

    LIMIT 1
");

$stmt->execute([$userId]);

$user = $stmt->fetch();


if (!$user) {

    session_destroy();

    header('Location: login.php');
    exit;
}


// =========================================
// ตรวจสอบสิทธิ์
// =========================================

$isAdmin = (($user['role'] ?? '') === 'admin');


// =========================================
// ตรวจสอบสถานะสมาชิก
// =========================================

$isMember =
    !$isAdmin &&
    !empty($user['student_id']);

$memberInfo = null;

if ($isMember) {
    $memberInfo = getAutomaticMemberStatus($user);
}


// =========================================
// ตั้งค่าหน้า
// =========================================

$pageTitle = 'โปรไฟล์ของฉัน';
$pageCss = 'profile.css';

include 'includes/header.php';

?>


<section class="profile-page">

    <div class="profile-container">


        <!-- =================================
             หัวข้อ
        ================================== -->

        <div class="profile-heading">

            <div>

                <span>
                    MY PROFILE
                </span>

                <h1>
                    โปรไฟล์ของฉัน
                </h1>

                <p>
                    ดูและแก้ไขข้อมูลส่วนตัวของคุณ
                </p>

            </div>

        </div>


        <!-- =================================
             ข้อความแจ้งเตือน
        ================================== -->

        <?php if ($success): ?>

            <div class="profile-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="profile-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="profile-layout">


            <!-- =================================
                 ด้านซ้าย
            ================================== -->

            <aside class="profile-card">


                <!-- รูปโปรไฟล์ -->

                <div class="profile-avatar">

                    <?php if (!empty($user['profile_image'])): ?>

                        <img
                            src="uploads/profiles/<?= htmlspecialchars(basename($user['profile_image'])) ?>"
                            alt="Profile"
                        >

                    <?php else: ?>

                        <span>

                            <?= htmlspecialchars(
                                mb_substr(
                                    $user['first_name'],
                                    0,
                                    1
                                )
                            ) ?>

                        </span>

                    <?php endif; ?>

                </div>


                <!-- ชื่อ -->

                <h2>

                    <?= htmlspecialchars(
                        $user['first_name'] .
                        ' ' .
                        $user['last_name']
                    ) ?>

                </h2>


                <!-- อีเมล -->

                <p>

                    <?= htmlspecialchars(
                        $user['email']
                    ) ?>

                </p>


                <!-- =================================
                     สถานะ
                ================================== -->

                <?php if ($isAdmin): ?>

                    <span class="profile-status active">

                        <i class="fa-solid fa-user-shield"></i>

                        ผู้ดูแลระบบ

                    </span>


                <?php elseif ($isMember): ?>


                    <?php if (
                        $memberInfo &&
                        $memberInfo['code'] === 'active'
                    ): ?>

                        <span class="profile-status active">

                            <i class="fa-solid fa-circle-check"></i>

                            สมาชิกชมรม

                        </span>


                    <?php elseif (
                        $memberInfo &&
                        $memberInfo['code'] === 'graduated'
                    ): ?>

                        <span class="profile-status graduated">

                            <i class="fa-solid fa-user-graduate"></i>

                            พ้นสภาพสมาชิก

                        </span>


                    <?php else: ?>

                        <span class="profile-status inactive">

                            <?= htmlspecialchars(
                                $memberInfo['text'] ?? 'สมาชิก'
                            ) ?>

                        </span>

                    <?php endif; ?>


                <?php else: ?>

                    <span class="profile-status normal">

                        บัญชีผู้ใช้งานทั่วไป

                    </span>

                <?php endif; ?>


                <div class="profile-side-line"></div>


                <!-- =================================
                     เมนูด้านซ้าย
                ================================== -->

                <div class="profile-side-menu">


                    <!-- ข้อมูลส่วนตัว -->

                    <a
                        href="profile.php"
                        class="active"
                    >

                        <i class="fa-regular fa-user"></i>

                        ข้อมูลส่วนตัว

                    </a>


                    <!-- เปลี่ยนรหัสผ่าน -->

                    <a href="change_password.php">

                        <i class="fa-solid fa-key"></i>

                        เปลี่ยนรหัสผ่าน

                    </a>


                    <!-- =================================
                         เมนูสมาชิก / ผู้ใช้งานทั่วไป
                    ================================== -->

                    <?php if (!$isAdmin): ?>


                        <a href="member_status.php">

                            <i class="fa-regular fa-id-card"></i>

                            สถานะสมาชิก

                        </a>


                        <a href="activities.php?my=1">

                            <i class="fa-regular fa-calendar-check"></i>

                            กิจกรรมของฉัน

                        </a>


                    <?php endif; ?>


                    <!-- =================================
                         เมนู Admin
                    ================================== -->

                    <?php if ($isAdmin): ?>


                        <a href="<?= BC_BASE_PATH ?>/admin/index.php">

                            <i class="fa-solid fa-gauge-high"></i>

                            ระบบผู้ดูแล

                        </a>


                    <?php endif; ?>


                </div>


            </aside>


            <!-- =================================
                 ด้านขวา
            ================================== -->

            <div class="profile-main">


                <!-- =================================
                     ข้อมูลส่วนตัว
                ================================== -->

                <div class="profile-form-card">


                    <div class="profile-section-title">


                        <div class="profile-section-icon">

                            <i class="fa-regular fa-user"></i>

                        </div>


                        <div>

                            <h3>
                                ข้อมูลส่วนตัว
                            </h3>

                            <p>
                                ข้อมูลสำหรับบัญชีผู้ใช้งาน
                            </p>

                        </div>


                    </div>


                    <!-- =================================
                         แบบฟอร์ม
                    ================================== -->

                    <form
                        method="POST"
                        action="profile.php"
                        class="profile-form"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($profileCsrf) ?>"
                        >

                        <div class="profile-photo-editor">

                            <div class="profile-photo-preview" id="profilePhotoPreview">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img
                                        src="uploads/profiles/<?= htmlspecialchars(basename($user['profile_image'])) ?>"
                                        alt="ตัวอย่างรูปโปรไฟล์"
                                    >
                                <?php else: ?>
                                    <span>
                                        <?= htmlspecialchars(mb_substr($user['first_name'], 0, 1)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="profile-photo-content">
                                <strong>รูปโปรไฟล์</strong>
                                <p>เลือกไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</p>

                                <div class="profile-photo-actions">
                                    <label class="profile-photo-select" for="profileImageInput">
                                        <i class="fa-regular fa-image"></i>
                                        เลือกรูปภาพ
                                    </label>
                                    <input
                                        type="file"
                                        name="profile_image"
                                        id="profileImageInput"
                                        accept="image/jpeg,image/png,image/webp"
                                    >

                                    <?php if (!empty($user['profile_image'])): ?>
                                        <label class="profile-photo-remove">
                                            <input type="checkbox" name="remove_profile_image" value="1">
                                            ลบรูปปัจจุบัน
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>


                        <div class="profile-form-grid">


                            <!-- ชื่อ -->

                            <div class="profile-form-group">

                                <label>

                                    ชื่อ

                                    <span>*</span>

                                </label>


                                <input
                                    type="text"
                                    name="first_name"
                                    value="<?= htmlspecialchars(
                                        $user['first_name']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- นามสกุล -->

                            <div class="profile-form-group">

                                <label>

                                    นามสกุล

                                    <span>*</span>

                                </label>


                                <input
                                    type="text"
                                    name="last_name"
                                    value="<?= htmlspecialchars(
                                        $user['last_name']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- อีเมล -->

                            <div class="profile-form-group full">

                                <label>

                                    อีเมล

                                    <span>*</span>

                                </label>


                                <input
                                    type="email"
                                    name="email"
                                    value="<?= htmlspecialchars(
                                        $user['email']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- เบอร์โทร -->

                            <?php if (!$isAdmin): ?>

                                <div class="profile-form-group full">

                                    <label>
                                        เบอร์โทรศัพท์
                                    </label>


                                    <input
                                        type="text"
                                        name="phone"
                                        value="<?= htmlspecialchars(
                                            $user['phone'] ?? ''
                                        ) ?>"
                                        placeholder="<?= $isMember
                                            ? 'กรอกเบอร์โทรศัพท์'
                                            : 'สามารถเพิ่มได้เมื่อเป็นสมาชิกชมรม'
                                        ?>"
                                    >

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- ปุ่มบันทึก -->

                        <div class="profile-actions">


                            <button
                                type="submit"
                                class="profile-save-btn"
                            >

                                <i class="fa-solid fa-floppy-disk"></i>

                                บันทึกข้อมูล

                            </button>


                        </div>


                    </form>


                </div>


                <!-- =================================
                     ข้อมูลสมาชิก
                ================================== -->

                <?php if ($isMember): ?>


                    <div class="profile-form-card member-detail">


                        <div class="profile-section-title">


                            <div class="profile-section-icon">

                                <i class="fa-solid fa-graduation-cap"></i>

                            </div>


                            <div>

                                <h3>
                                    ข้อมูลสมาชิกชมรม
                                </h3>

                                <p>
                                    ข้อมูลการศึกษาและสถานะสมาชิก
                                </p>

                            </div>


                        </div>


                        <div class="profile-detail-grid">


                            <!-- รหัสนิสิต -->

                            <div>

                                <span>
                                    รหัสนิสิต
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user['student_id']
                                    ) ?>

                                </strong>

                            </div>


                            <!-- ชั้นปี -->

                            <div>

                                <span>
                                    ชั้นปีปัจจุบัน
                                </span>


                                <strong>


                                    <?php if (
                                        $memberInfo &&
                                        $memberInfo['code'] === 'active'
                                    ): ?>


                                        ปี <?= (int)$memberInfo['year_level'] ?>


                                    <?php elseif (
                                        $memberInfo &&
                                        $memberInfo['code'] === 'graduated'
                                    ): ?>


                                        พ้นสภาพสมาชิก


                                    <?php else: ?>


                                        -


                                    <?php endif; ?>


                                </strong>

                            </div>


                            <!-- คณะ -->

                            <div>

                                <span>
                                    คณะ
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user['faculty'] ?: '-'
                                    ) ?>

                                </strong>

                            </div>


                            <!-- สาขา -->

                            <div>

                                <span>
                                    สาขา
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user['major'] ?: '-'
                                    ) ?>

                                </strong>

                            </div>


                        </div>


                    </div>


                <?php endif; ?>


                <!-- =================================
                     ยังไม่ได้เป็นสมาชิก
                ================================== -->

                <?php if (
                    !$isMember &&
                    !$isAdmin
                ): ?>


                    <div class="profile-join-card">


                        <div>


                            <i class="fa-solid fa-users"></i>


                            <h3>

                                สนใจเข้าร่วม Bizcomp Club?

                            </h3>


                            <p>

                                สมัครสมาชิกชมรมเพื่อเข้าร่วมกิจกรรม
                                และเป็นส่วนหนึ่งของ Bizcomp Club

                            </p>


                        </div>


                        <a
                            href="member_status.php"
                            class="profile-join-btn"
                        >

                            ดูสถานะ / สมัครสมาชิก

                        </a>


                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('profilePhotoPreview');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.addEventListener('load', function () {
            preview.innerHTML = '';
            const image = document.createElement('img');
            image.src = reader.result;
            image.alt = 'ตัวอย่างรูปโปรไฟล์ใหม่';
            preview.appendChild(image);
        });
        reader.readAsDataURL(file);
    });
});
</script>


<?php include 'includes/footer.php'; ?>
