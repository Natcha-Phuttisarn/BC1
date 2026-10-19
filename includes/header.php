<?php
// header.php
// ไฟล์นี้ใช้สำหรับแสดง Header และเมนูหลักของเว็บไซต์ทุกหน้า

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

$pageTitle = $pageTitle ?? 'Bizcomp Club';
$pageCss = $pageCss ?? '';

$siteName = 'Bizcomp Club';

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $siteNameStmt = $pdo->prepare("
            SELECT setting_value
            FROM site_settings
            WHERE setting_key = 'club_name'
            LIMIT 1
        ");
        $siteNameStmt->execute();
        $configuredSiteName = trim((string)$siteNameStmt->fetchColumn());

        if ($configuredSiteName !== '') {
            $siteName = $configuredSiteName;
        }
    } catch (Throwable $e) {
        // ใช้ชื่อเริ่มต้นเมื่อยังไม่มีตารางตั้งค่า
    }
}


// =========================================
// LOGIN
// =========================================

$isLoggedIn = isset($_SESSION['user_id']);


// =========================================
// ADMIN
// =========================================

$isAdmin =
    $isLoggedIn
    && ($_SESSION['role'] ?? '') === 'admin';


// =========================================
// สิทธิ์แสดงเมนูรายชื่อสมาชิก
// =========================================

$canViewMemberDirectory = false;

if ($isLoggedIn) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/db.php';
    }

    require_once __DIR__ . '/functions.php';

    try {
        $memberMenuStmt = $pdo->prepare("
            SELECT
                u.account_status,
                m.*,
                (
                    SELECT ma.application_status
                    FROM membership_applications ma
                    WHERE ma.user_id = u.id
                    ORDER BY ma.id DESC
                    LIMIT 1
                ) AS latest_application_status
            FROM users u
            LEFT JOIN members m
                ON m.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");

        $memberMenuStmt->execute([
            (int)$_SESSION['user_id']
        ]);

        $memberMenuAccess =
            $memberMenuStmt->fetch(PDO::FETCH_ASSOC);

        if (
            $memberMenuAccess &&
            !empty($memberMenuAccess['id']) &&
            ($memberMenuAccess['account_status'] ?? '') === 'active' &&
            ($memberMenuAccess['member_status'] ?? '') === 'active' &&
            ($memberMenuAccess['latest_application_status'] ?? '') === 'approved'
        ) {
            $memberMenuAutomaticStatus =
                getAutomaticMemberStatus($memberMenuAccess);

            $canViewMemberDirectory =
                ($memberMenuAutomaticStatus['code'] ?? '') === 'active';
        }
    } catch (Throwable $e) {
        // หากตรวจสอบสิทธิ์ไม่ได้ ให้ซ่อนเมนูไว้ก่อน
        $canViewMemberDirectory = false;
    }
}


// =========================================
// ชื่อที่แสดง
// =========================================

$displayName = !empty($_SESSION['first_name'])
    ? $_SESSION['first_name']
    : 'บัญชีของฉัน';


// =========================================
// ACTIVE MENU
// =========================================

$isHome =
    $currentPage === 'index.php';

$isAbout = in_array(
    $currentPage,
    [
        'about.php',
        'history.php',
        'goals.php',
        'team.php',
        'user_guide.php'
    ],
    true
);

$isActivities =
    $currentPage === 'activities.php';

$isMembers =
    $currentPage === 'members.php';

$isContact =
    $currentPage === 'contact.php';

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($siteName) ?>
    </title>

    <meta name="application-name" content="<?= htmlspecialchars($siteName) ?>">
    <link rel="icon" type="image/png" href="<?= BC_BASE_PATH ?>/assets/images/logo.png">


    <!-- =====================================
         GOOGLE FONT
    ====================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">


    <!-- =====================================
         FONT AWESOME
    ====================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">


    <!-- =====================================
         CSS หลัก
    ====================================== -->

    <link
        rel="stylesheet"
        href="<?= BC_BASE_PATH ?>/assets/css/style.css">

    <link
        rel="stylesheet"
        href="<?= BC_BASE_PATH ?>/assets/css/header.css">

    <link
        rel="stylesheet"
        href="<?= BC_BASE_PATH ?>/assets/css/footer.css">


    <!-- =====================================
         CSS เฉพาะหน้า
    ====================================== -->

    <?php if ($pageCss !== ''): ?>

        <link
            rel="stylesheet"
            href="<?= BC_BASE_PATH ?>/assets/css/<?= htmlspecialchars($pageCss) ?>">

    <?php endif; ?>


    <!-- =====================================
         HEADER JS
    ====================================== -->

    <script
        src="<?= BC_BASE_PATH ?>/assets/js/header.js"
        defer></script>

<script src="<?= BC_BASE_PATH ?>/assets/js/image-optimize.js" defer></script>
</head>

<body>


    <!-- =========================================
     HEADER
========================================= -->

    <header class="bc-header">

        <div class="bc-header-container">


            <!-- =================================
             LOGO
        ================================== -->

            <a
                href="<?= BC_BASE_PATH ?>/index.php"
                class="bc-brand">

                <img
                    src="<?= BC_BASE_PATH ?>/assets/images/logo.png"
                    alt="<?= htmlspecialchars($siteName) ?>"
                    class="bc-brand-logo">

                <div class="bc-brand-text">

                    <strong>
                        BIZCOMP CLUB
                    </strong>

                </div>

            </a>


            <!-- =================================
             MOBILE BUTTON
        ================================== -->

            <button
                type="button"
                class="bc-mobile-btn"
                id="bcMobileBtn"
                aria-label="เปิดเมนู">

                <i class="fa-solid fa-bars"></i>

            </button>


            <!-- =================================
             NAV AREA
        ================================== -->

            <div
                class="bc-nav-area"
                id="bcNavArea">


                <!-- =================================
                 NAVIGATION
            ================================== -->

                <nav class="bc-nav">


                    <!-- หน้าหลัก -->

                    <a
                        href="<?= BC_BASE_PATH ?>/index.php"
                        class="bc-nav-link <?= $isHome ? 'active' : '' ?>">
                        หน้าหลัก
                    </a>


                    <!-- =================================
                     เกี่ยวกับชมรม
                ================================== -->

                    <div
                        class="bc-dropdown <?= $isAbout ? 'active' : '' ?>"
                        id="bcAboutDropdown">

                        <button
                            type="button"
                            class="bc-dropdown-btn"
                            id="bcAboutBtn"
                            aria-expanded="false">

                            <span>
                                เกี่ยวกับชมรม
                            </span>

                            <i class="fa-solid fa-angle-down"></i>

                        </button>


                        <div
                            class="bc-dropdown-menu"
                            id="bcAboutMenu">


                            <!-- ประวัติ -->

                            <a
                                href="<?= BC_BASE_PATH ?>/history.php"
                                class="<?= $currentPage === 'history.php'
                                            ? 'current'
                                            : '' ?>">

                                <div class="bc-sub-icon">

                                    <i class="fa-solid fa-clock-rotate-left"></i>

                                </div>

                                <div>

                                    <strong>
                                        ประวัติความเป็นมา
                                    </strong>

                                    <span>
                                        ความเป็นมาของ Bizcomp Club
                                    </span>

                                </div>

                            </a>


                            <!-- วัตถุประสงค์ -->

                            <a
                                href="<?= BC_BASE_PATH ?>/goals.php"
                                class="<?= $currentPage === 'goals.php'
                                            ? 'current'
                                            : '' ?>">

                                <div class="bc-sub-icon">

                                    <i class="fa-solid fa-bullseye"></i>

                                </div>

                                <div>

                                    <strong>
                                        วัตถุประสงค์
                                    </strong>

                                    <span>
                                        เป้าหมายและแนวทางของชมรม
                                    </span>

                                </div>

                            </a>


                            <!-- บุคลากร -->

                            <a
                                href="<?= BC_BASE_PATH ?>/team.php"
                                class="<?= $currentPage === 'team.php'
                                            ? 'current'
                                            : '' ?>">

                                <div class="bc-sub-icon">

                                    <i class="fa-solid fa-users"></i>

                                </div>

                                <div>

                                    <strong>
                                        บุคลากรชมรม
                                    </strong>

                                    <span>
                                        อาจารย์ที่ปรึกษาและคณะกรรมการ
                                    </span>

                                </div>

                            </a>


                            <!-- วิธีการใช้งานระบบ -->

                            <a
                                href="<?= BC_BASE_PATH ?>/user_guide.php"
                                class="<?= $currentPage === 'user_guide.php'
                                            ? 'current'
                                            : '' ?>">

                                <div class="bc-sub-icon">

                                    <i class="fa-solid fa-book-open"></i>

                                </div>

                                <div>

                                    <strong>
                                        วิธีการใช้งานระบบ
                                    </strong>

                                    <span>
                                        คู่มือการใช้งานเว็บไซต์สำหรับสมาชิก
                                    </span>

                                </div>

                            </a>

                        </div>

                    </div>


                    <!-- =================================
                     กิจกรรม
                ================================== -->

                    <a
                        href="<?= BC_BASE_PATH ?>/activities.php"
                        class="bc-nav-link <?= $isActivities ? 'active' : '' ?>">
                        กิจกรรม
                    </a>


                    <!-- =================================
                     รายชื่อสมาชิก
                ================================== -->

                    <?php if ($canViewMemberDirectory): ?>

                        <a
                            href="<?= BC_BASE_PATH ?>/members.php"
                            class="bc-nav-link <?= $isMembers ? 'active' : '' ?>">
                            รายชื่อสมาชิก
                        </a>

                    <?php endif; ?>


                    <!-- =================================
                     ติดต่อเรา
                ================================== -->

                    <a
                        href="<?= BC_BASE_PATH ?>/contact.php"
                        class="bc-nav-link <?= $isContact ? 'active' : '' ?>">
                        ติดต่อเรา
                    </a>

                </nav>


                <!-- =================================
                 ACCOUNT
            ================================== -->

                <div class="bc-account">


                    <!-- =================================
                     ยังไม่ Login
                ================================== -->

                    <?php if (!$isLoggedIn): ?>

                        <a
                            href="<?= BC_BASE_PATH ?>/login.php"
                            class="bc-login-btn">

                            <i class="fa-regular fa-user"></i>

                            <span>
                                เข้าสู่ระบบ
                            </span>

                        </a>


                        <!-- =================================
                     Login แล้ว
                ================================== -->

                    <?php else: ?>


                        <div
                            class="bc-user"
                            id="bcUserDropdown">


                            <!-- ปุ่มชื่อผู้ใช้ -->

                            <button
                                type="button"
                                class="bc-user-btn"
                                id="bcUserBtn"
                                aria-expanded="false">

                                <div class="bc-user-avatar">
                                    <?php if (!empty($_SESSION['profile_image'])): ?>
                                        <img
                                            src="<?= BC_BASE_PATH ?>/uploads/profiles/<?= htmlspecialchars(basename($_SESSION['profile_image'])) ?>"
                                            alt="รูปโปรไฟล์"
                                        >
                                    <?php else: ?>
                                        <?= htmlspecialchars(
                                            mb_substr(
                                                $displayName,
                                                0,
                                                1
                                            )
                                        ) ?>
                                    <?php endif; ?>

                                </div>


                                <span>
                                    <?= htmlspecialchars($displayName) ?>
                                </span>


                                <i class="fa-solid fa-chevron-down"></i>

                            </button>


                            <!-- =================================
                             USER DROPDOWN
                        ================================== -->

                            <div
                                class="bc-user-menu"
                                id="bcUserMenu">


                                <!-- =================================
                                 ADMIN DASHBOARD
                                 แสดงเฉพาะ Admin
                            ================================== -->

                                <?php if ($isAdmin): ?>

                                    <a
                                        href="<?= BC_BASE_PATH ?>/admin/index.php"
                                        class="bc-admin-menu-link">

                                        <i class="fa-solid fa-gauge-high"></i>

                                        ระบบผู้ดูแล

                                    </a>


                                    <div class="bc-user-divider"></div>

                                <?php endif; ?>


                                <!-- ข้อมูลส่วนตัว -->

                                <a href="<?= BC_BASE_PATH ?>/profile.php">

                                    <i class="fa-regular fa-user"></i>

                                    ข้อมูลส่วนตัว

                                </a>
                                <a href="<?= BC_BASE_PATH ?>/change_password.php">
                                    <i class="fa-solid fa-key"></i>
                                    เปลี่ยนรหัสผ่าน
                                </a>

                                <!-- =================================
                                 เมนูสมาชิก
                                 Admin ไม่จำเป็นต้องเห็น
                            ================================== -->

                                <?php if (!$isAdmin): ?>

                                    <a href="<?= BC_BASE_PATH ?>/member_status.php">

                                        <i class="fa-regular fa-id-card"></i>

                                        สถานะสมาชิก

                                    </a>


                                    <a href="<?= BC_BASE_PATH ?>/activities.php?my=1">

                                        <i class="fa-regular fa-calendar-check"></i>

                                        กิจกรรมของฉัน

                                    </a>

                                <?php endif; ?>


                                <div class="bc-user-divider"></div>


                                <!-- Logout -->

                                <a
                                    href="<?= BC_BASE_PATH ?>/logout.php"
                                    class="bc-logout">

                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>

                                    ออกจากระบบ

                                </a>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>

            </div>

        </div>

    </header>
