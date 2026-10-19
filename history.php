<?php
require_once __DIR__ . '/includes/bootstrap.php';
// history.php
// หน้านี้ใช้สำหรับแสดงประวัติความเป็นมาของชมรม Bizcomp Club

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'ประวัติความเป็นมา';
$pageCss = 'history.css';

include 'includes/header.php';
?>

<main class="history-page">

    <!-- HERO -->
    <section class="history-hero">
        <div class="history-container history-hero-inner">

            <div class="history-hero-content">

                <div class="history-breadcrumb">
                    <a href="index.php">หน้าหลัก</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span>เกี่ยวกับชมรม</span>
                    <i class="fa-solid fa-chevron-right"></i>
                    <strong>ประวัติความเป็นมา</strong>
                </div>

                <span class="history-label">
                    ABOUT BIZCOMP CLUB
                </span>

                <h1>
                    ประวัติความเป็นมา
                </h1>

                <h2>
                    BIZCOMP CLUB
                </h2>

                <p>
                    ชมรมบิตคอม
                    คณะการบัญชีและการจัดการ
                    มหาวิทยาลัยมหาสารคาม
                </p>

                <div class="history-hero-line"></div>

            </div>

            <div class="history-hero-visual">

                <div class="history-logo-circle">
                    <img
                        src="assets/images/logo.png"
                        alt="Bizcomp Club"
                    >
                </div>

                <div class="history-quote">
                    <i class="fa-solid fa-quote-left"></i>

                    <span>
                        มุ่งมั่น สานสัมพันธ์
                        <br>
                        ด้วยเทคโนโลยี
                    </span>
                </div>

            </div>

        </div>
    </section>


    <!-- ประวัติ -->
    <section class="history-content-section">

        <div class="history-container">

            <div class="history-content-grid">

                <div class="history-main-card">

                    <div class="history-section-title">

                        <div class="history-title-icon">
                            <i class="fa-solid fa-book-open"></i>
                        </div>

                        <div>
                            <span>OUR HISTORY</span>
                            <h2>จุดเริ่มต้นของชมรม</h2>
                        </div>

                    </div>

                    <div class="history-text">

                        <p>
                            ชมรมบิซคอมพ์ คลับ
                            (Biz Comp Club)
                            เป็นชมรมภายใต้สโมสรนิสิต
                            คณะการบัญชีและการจัดการ
                            มหาวิทยาลัยมหาสารคาม
                            จัดตั้งขึ้นเพื่อเป็นพื้นที่สำหรับนิสิต
                            ในการทำกิจกรรมร่วมกัน
                            และพัฒนาความรู้ความสามารถ
                            ที่เกี่ยวข้องกับคอมพิวเตอร์ธุรกิจ
                            และเทคโนโลยีสารสนเทศ
                        </p>

                        <p>
                            ชมรมมุ่งเน้นการสร้างความสัมพันธ์ที่ดี
                            ระหว่างสมาชิก ส่งเสริมการมีส่วนร่วม
                            การทำงานร่วมกัน และการใช้ความรู้
                            ด้านเทคโนโลยีให้เกิดประโยชน์
                            ทั้งต่อสมาชิก ชมรม และส่วนรวม
                        </p>

                        <p>
                            ในปีการศึกษา 2569
                            ชมรมดำเนินงานภายใต้
                            คณะการบัญชีและการจัดการ
                            มหาวิทยาลัยมหาสารคาม
                            พร้อมจัดกิจกรรมเพื่อเสริมสร้าง
                            ประสบการณ์และพัฒนาศักยภาพของนิสิต
                            อย่างต่อเนื่อง
                        </p>

                    </div>

                </div>


                <!-- คำขวัญ -->
                <aside class="history-motto-card">

                    <div class="motto-icon">
                        <i class="fa-solid fa-quote-left"></i>
                    </div>

                    <span class="motto-small">
                        คำขวัญชมรม
                    </span>

                    <h3>
                        “มุ่งมั่น
                        <br>
                        สานสัมพันธ์
                        <br>
                        ด้วยเทคโนโลยี”
                    </h3>

                    <div class="motto-line"></div>

                    <p>
                        BIZCOMP CLUB
                    </p>

                </aside>

            </div>

        </div>

    </section>


    <!-- ความหมายชื่อ -->
    <section class="history-meaning-section">

        <div class="history-container">

            <div class="history-section-head">

                <span>
                    CLUB IDENTITY
                </span>

                <h2>
                    ความหมายของชื่อชมรม
                </h2>

                <p>
                    ความหมายของคำที่ประกอบกันเป็น
                    Biz Comp Club
                </p>

            </div>


            <div class="history-meaning-grid">

                <article class="meaning-card">

                    <div class="meaning-letter">
                        B
                    </div>

                    <div>
                        <span>BIZ</span>

                        <h3>
                            Business
                        </h3>

                        <p>
                            หมายถึง ธุรกิจ
                        </p>
                    </div>

                </article>


                <article class="meaning-card">

                    <div class="meaning-letter">
                        C
                    </div>

                    <div>
                        <span>COMP</span>

                        <h3>
                            Computer
                        </h3>

                        <p>
                            หมายถึง คอมพิวเตอร์
                        </p>
                    </div>

                </article>


                <article class="meaning-card">

                    <div class="meaning-letter">
                        C
                    </div>

                    <div>
                        <span>CLUB</span>

                        <h3>
                            Club
                        </h3>

                        <p>
                            หมายถึง กลุ่มหรือชมรม
                        </p>
                    </div>

                </article>

            </div>


            <div class="history-meaning-result">

                <div>
                    <i class="fa-solid fa-users"></i>
                </div>

                <p>
                    <span>Biz Comp Club</span>
                    หมายถึง
                    <strong>กลุ่มคอมพิวเตอร์ธุรกิจ</strong>
                </p>

            </div>

        </div>

    </section>


    <!-- ปิดท้าย -->
    <section class="history-bottom">

        <div class="history-container">

            <div class="history-bottom-card">

                <div class="history-bottom-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>

                <div>
                    <small>
                        BIZCOMP CLUB
                    </small>

                    <h2>
                        เรียนรู้ พัฒนา และเติบโตไปด้วยกัน
                    </h2>

                    <p>
                        พื้นที่สำหรับการเรียนรู้
                        การทำกิจกรรม และการสร้างประสบการณ์ร่วมกันของสมาชิกชมรม
                    </p>
                </div>

                <a href="goals.php">
                    ดูวัตถุประสงค์
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>

        </div>

    </section>

</main>

<?php include 'includes/footer.php'; ?>
