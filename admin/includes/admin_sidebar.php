<?php
// admin_sidebar.php
// ไฟล์นี้ใช้สำหรับแสดง Sidebar และ Topbar ของระบบผู้ดูแล Bizcomp Club

$uri = $_SERVER['REQUEST_URI'];


// =========================================
// ตรวจสอบเมนูที่กำลังเปิด
// =========================================

$isDashboard =
    strpos($uri, '/admin/index.php') !== false;

$isActivities =
    strpos($uri, '/admin/activities/') !== false;

$isGallery =
    strpos($uri, '/admin/gallery/') !== false;

$isRegistrations =
    strpos($uri, '/admin/registrations/') !== false;

$isMembers =
    strpos($uri, '/admin/members/') !== false;

$isUsers =
    strpos($uri, '/admin/users/') !== false;

$isMembership =
    strpos($uri, '/admin/membership/') !== false;

$isPayments =
    strpos($uri, '/admin/payments/') !== false;

$isReports =
    strpos($uri, '/admin/reports/') !== false;

$isTeam =
    strpos($uri, '/admin/team/') !== false;

$isSettings =
    strpos($uri, '/admin/settings/') !== false;

$isAudit = strpos($uri, '/admin/audit/') !== false;
$isBackup = strpos($uri, '/admin/backup/') !== false;
$isFinance = strpos($uri, '/admin/finance/') !== false;

?>


<!-- =========================================
     SIDEBAR
========================================= -->

<aside
    class="admin-sidebar"
    id="adminSidebar"
>


    <!-- =====================================
         LOGO
    ====================================== -->

    <div class="sidebar-brand">

        <img
            src="<?= BC_BASE_PATH ?>/assets/images/logo.png"
            alt="Bizcomp Club"
        >

        <div>

            <strong>
                BIZCOMP CLUB
            </strong>

            <span>
                ADMINISTRATION
            </span>

        </div>

    </div>


    <!-- =====================================
         เมนูหลัก
    ====================================== -->

    <div class="sidebar-label">
        เมนูหลัก
    </div>


    <nav class="sidebar-menu">


        <!-- =================================
             Dashboard
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/index.php"
            class="<?= $isDashboard ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-house"></i>

            </span>

            <span>
                แดชบอร์ด
            </span>

        </a>


        <!-- =================================
             จัดการกิจกรรม
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/activities/index.php"
            class="<?= $isActivities ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-regular fa-calendar-days"></i>

            </span>

            <span>
                จัดการกิจกรรม
            </span>

        </a>


        <!-- =================================
             ภาพกิจกรรม
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/gallery/index.php"
            class="<?= $isGallery ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-regular fa-images"></i>

            </span>

            <span>
                ภาพกิจกรรม
            </span>

        </a>


        <!-- =================================
             ผู้สมัครกิจกรรม
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/registrations/index.php"
            class="<?= $isRegistrations ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-clipboard-user"></i>

            </span>

            <span>
                ผู้สมัครกิจกรรม
            </span>

        </a>


        <!-- =================================
             สมาชิกชมรม
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/members/index.php"
            class="<?= $isMembers ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-users"></i>

            </span>

            <span>
                สมาชิกชมรม
            </span>

        </a>


        <a
            href="<?= BC_BASE_PATH ?>/admin/users/index.php"
            class="<?= $isUsers ? 'active' : '' ?>"
        >
            <span class="menu-icon">
                <i class="fa-solid fa-user-gear"></i>
            </span>
            <span>บัญชีผู้ใช้งาน</span>
        </a>


        <!-- =================================
             คำขอสมัครสมาชิก
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/membership/applications.php"
            class="<?= $isMembership ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-user-plus"></i>

            </span>

            <span>
                คำขอสมัครสมาชิก
            </span>

        </a>


        <!-- =================================
             การชำระเงิน
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/payments/index.php"
            class="<?= $isPayments ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-regular fa-credit-card"></i>

            </span>

            <span>
                การชำระเงิน
            </span>

        </a>


        <!-- =====================================
             รายงาน
        ====================================== -->

        <div class="sidebar-label second">
            รายงาน
        </div>


        <!-- =================================
             สรุปสมาชิก
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/reports/members.php"
            class="<?= $isReports ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-chart-pie"></i>

            </span>

            <span>
                สรุปสมาชิก / รายงาน
            </span>

        </a>


        <!-- =====================================
             จัดการเว็บไซต์
        ====================================== -->

        <div class="sidebar-label second">
            จัดการเว็บไซต์
        </div>


        <!-- =================================
             บุคลากร
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/team/index.php"
            class="<?= $isTeam ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-user-tie"></i>

            </span>

            <span>
                บุคลากร
            </span>

        </a>


        <!-- =================================
             ตั้งค่าเว็บไซต์
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/settings/index.php"
            class="<?= $isSettings ? 'active' : '' ?>"
        >

            <span class="menu-icon">

                <i class="fa-solid fa-gear"></i>

            </span>

            <span>
                ตั้งค่าเว็บไซต์
            </span>

        </a>

        <a href="<?= BC_BASE_PATH ?>/admin/audit/index.php" class="<?= $isAudit ? 'active' : '' ?>">
            <span class="menu-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span>ประวัติการแก้ไข</span>
        </a>

        <a href="<?= BC_BASE_PATH ?>/admin/backup/index.php" class="<?= $isBackup ? 'active' : '' ?>">
            <span class="menu-icon"><i class="fa-solid fa-database"></i></span>
            <span>สำรองฐานข้อมูล</span>
        </a>

        <a href="<?= BC_BASE_PATH ?>/admin/finance/index.php" class="<?= $isFinance ? 'active' : '' ?>">
            <span class="menu-icon"><i class="fa-solid fa-wallet"></i></span>
            <span>บัญชีรายรับ–รายจ่าย</span>
        </a>


    </nav>


    <!-- =====================================
         เมนูด้านล่าง
    ====================================== -->

    <div class="sidebar-bottom">


        <!-- =================================
             ดูหน้าเว็บไซต์
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/index.php"
            target="_blank"
        >

            <i class="fa-solid fa-arrow-up-right-from-square"></i>

            <span>
                ดูหน้าเว็บไซต์
            </span>

        </a>


        <!-- =================================
             Logout
        ================================== -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/logout.php"
            class="logout-link"
        >

            <i class="fa-solid fa-arrow-right-from-bracket"></i>

            <span>
                ออกจากระบบ
            </span>

        </a>


    </div>

</aside>


<!-- =========================================
     MAIN
========================================= -->

<div class="admin-main">


    <!-- =====================================
         TOPBAR
    ====================================== -->

    <header class="admin-topbar">

        <div class="topbar-left">


            <!-- =================================
                 ปุ่มเมนูมือถือ
            ================================== -->

            <button
                class="sidebar-toggle"
                id="sidebarToggle"
                type="button"
                aria-label="เปิดเมนู"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <!-- =================================
                 ชื่อหน้า
            ================================== -->

            <div>

                <h1>

                    <?= htmlspecialchars(
                        $adminPageTitle
                        ?? $pageTitle
                        ?? 'ผู้ดูแลระบบ'
                    ) ?>

                </h1>

                <p>
                    ระบบบริหารจัดการ Bizcomp Club
                </p>

            </div>

        </div>


        <!-- =================================
             ADMIN PROFILE
        ================================== -->

        <div class="admin-profile">

            <div class="admin-avatar">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img
                        src="<?= BC_BASE_PATH ?>/uploads/profiles/<?= htmlspecialchars(basename($_SESSION['profile_image'])) ?>"
                        alt="รูปโปรไฟล์"
                    >
                <?php else: ?>
                    <?= htmlspecialchars(
                        mb_substr(
                            $_SESSION['first_name'] ?? 'A',
                            0,
                            1
                        )
                    ) ?>
                <?php endif; ?>

            </div>


            <div class="admin-profile-text">

                <strong>

                    <?= htmlspecialchars(
                        $_SESSION['first_name'] ?? 'Admin'
                    ) ?>

                </strong>

                <span>
                    ผู้ดูแลระบบ
                </span>

            </div>

        </div>

    </header>


    <!-- =====================================
         CONTENT
    ====================================== -->

    <main class="admin-content">
        
