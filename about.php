<?php
require_once __DIR__ . '/includes/bootstrap.php';
// about.php
// หน้านี้เป็นหน้าหลักเกี่ยวกับชมรม และใช้เลือกไปยังประวัติ วัตถุประสงค์ และบุคลากร

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'เกี่ยวกับชมรม';
$pageCss = 'about.css';

include 'includes/header.php';
?>

<!-- =========================================
     HERO
========================================= -->

<section class="about-hero">

    <div class="about-hero-overlay"></div>

    <div class="about-container about-hero-inner">

        <div class="about-hero-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>

        <div class="about-hero-text">

            <span>ABOUT BIZCOMP CLUB</span>

            <h1>เกี่ยวกับชมรม</h1>

            <p>
                ทำความรู้จักกับชมรมบิตคอม
                คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
            </p>

        </div>

    </div>

</section>


<!-- =========================================
     INTRO
========================================= -->

<section class="about-intro">

    <div class="about-container">

        <div class="about-intro-box">

            <div class="about-intro-logo">

                <img
                    src="assets/images/logo.png"
                    alt="Bizcomp Club"
                >

            </div>

            <div class="about-intro-text">

                <span>BUSINESS COMPUTER CLUB</span>

                <h2>Bizcomp Club</h2>

                <h3>ชมรมบิตคอม</h3>

                <p>
                    ชมรมบิตคอม
                    คณะการบัญชีและการจัดการ
                    มหาวิทยาลัยมหาสารคาม
                    เป็นพื้นที่สำหรับการเรียนรู้
                    การพัฒนาทักษะ และการทำกิจกรรมร่วมกันของสมาชิกชมรม
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================================
     ABOUT MENU
========================================= -->

<section class="about-menu">

    <div class="about-container">

        <div class="about-heading">

            <span>ABOUT OUR CLUB</span>

            <h2>ข้อมูลเกี่ยวกับชมรม</h2>

            <div class="about-heading-line"></div>

            <p>
                เลือกหัวข้อที่ต้องการเพื่อดูรายละเอียด
            </p>

        </div>


        <div class="about-grid">


            <!-- =================================
                 HISTORY
            ================================== -->

            <a
                href="history.php"
                class="about-card"
            >

                <div class="about-card-icon">

                    <i class="fa-solid fa-clock-rotate-left"></i>

                </div>

                <div class="about-card-number">
                    01
                </div>

                <span>OUR HISTORY</span>

                <h3>
                    ประวัติความเป็นมา
                </h3>

                <p>
                    ดูข้อมูลความเป็นมาและที่มาของ
                    ชมรมบิตคอม
                </p>

                <div class="about-card-link">

                    อ่านรายละเอียด

                    <i class="fa-solid fa-arrow-right"></i>

                </div>

            </a>


            <!-- =================================
                 GOALS
            ================================== -->

            <a
                href="goals.php"
                class="about-card"
            >

                <div class="about-card-icon">

                    <i class="fa-solid fa-bullseye"></i>

                </div>

                <div class="about-card-number">
                    02
                </div>

                <span>OUR OBJECTIVES</span>

                <h3>
                    วัตถุประสงค์
                </h3>

                <p>
                    ดูวัตถุประสงค์และแนวทางการดำเนินงาน
                    ของ Bizcomp Club
                </p>

                <div class="about-card-link">

                    อ่านรายละเอียด

                    <i class="fa-solid fa-arrow-right"></i>

                </div>

            </a>


            <!-- =================================
                 TEAM
            ================================== -->

            <a
                href="team.php"
                class="about-card"
            >

                <div class="about-card-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

                <div class="about-card-number">
                    03
                </div>

                <span>OUR TEAM</span>

                <h3>
                    บุคลากรชมรม
                </h3>

                <p>
                    ดูข้อมูลอาจารย์ที่ปรึกษา
                    และคณะกรรมการบริหารชมรม
                </p>

                <div class="about-card-link">

                    ดูบุคลากร

                    <i class="fa-solid fa-arrow-right"></i>

                </div>

            </a>

        </div>

    </div>

</section>


<?php include 'includes/footer.php'; ?>
