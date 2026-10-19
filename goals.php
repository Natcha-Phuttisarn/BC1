<?php
require_once __DIR__ . '/includes/bootstrap.php';
// goals.php
// หน้านี้ใช้สำหรับแสดงวัตถุประสงค์ของชมรม Bizcomp Club

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'วัตถุประสงค์';
$pageCss = 'goals.css';

include 'includes/header.php';
?>

<main class="goals-page">

    <!-- HERO -->
    <section class="goals-hero">
        <div class="goals-container">

            <div class="goals-breadcrumb">
                <a href="index.php">หน้าหลัก</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span>เกี่ยวกับชมรม</span>
                <i class="fa-solid fa-chevron-right"></i>
                <strong>วัตถุประสงค์</strong>
            </div>

            <div class="goals-hero-grid">

                <div class="goals-hero-content">
                    <span class="goals-label">OUR OBJECTIVES</span>

                    <h1>วัตถุประสงค์ของชมรม</h1>
                    <h2>BIZCOMP CLUB</h2>

                    <p class="goals-hero-text">
                        “สร้างโอกาส พัฒนาศักยภาพ ร่วมขับเคลื่อนอนาคตด้วยเทคโนโลยีและความร่วมมือ”
                        
                    </p>
                </div>

            </div>

        </div>
    </section>


    <!-- INTRO -->
    <section class="goals-intro-section">
        <div class="goals-container">

            <div class="goals-intro-grid">

                <div class="goals-intro-left">
                    <div class="goals-intro-head">
                        <div class="goals-intro-icon">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>

                        <div>
                            <span class="goals-intro-label">OUR OBJECTIVES</span>
                            
                    <p class="goals-intro-text">
                        ชมรมบิซคอมพ์ คลับ มุ่งส่งเสริมให้นิสิตมีความรู้ ทักษะ
                        ประสบการณ์ และคุณลักษณะที่พร้อมสำหรับการเป็นนักเทคโนโลยีสารสนเทศ
                        นักธุรกิจ และผู้ประกอบการในอนาคต
                    </p>                       
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>


    <!-- OBJECTIVE LIST -->
    <section class="goals-list-section">
        <div class="goals-container">

            <div class="goals-grid">

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <div class="goal-content">
                        <h3>เพิ่มพูนความรู้ นอกห้องเรียน</h3>
                        <p>
                            เพื่อให้เกิดการเพิ่มพูนความรู้
                            นอกตำราเรียน นอกห้องเรียน
                            ให้กับสมาชิกและผู้ที่สนใจ
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="goal-content">
                        <h3>สนับสนุนการนำความรู้ไปใช้จริง</h3>
                        <p>
                            เพื่อเป็นการสนับสนุนให้สมาชิกและผู้ที่สนใจ
                            นำความรู้ที่ได้เรียนมาทั้งในและนอกห้องเรียน
                            มาประยุกต์ให้เกิดประโยชน์กับสภาพการทำงานจริง
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-laptop"></i>
                    </div>
                    <div class="goal-content">
                        <h3>เสริมสร้างประสบการณ์ด้านเทคโนโลยี</h3>
                        <p>
                            เพื่อให้เกิดการสร้างเสริมประสบการณ์ในด้านเทคโนโลยีสารสนเทศ
                            ให้กับสมาชิก จากการได้ทำงานกับสภาพการทำงานจริง
                            และจากการนำความรู้ที่ได้เรียนมาใช้กับสภาพการทำงานจริง
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-chart-column"></i>
                    </div>
                    <div class="goal-content">
                        <h3>เตรียมความพร้อมสู่การประกอบอาชีพ</h3>
                        <p>
                            เพื่อเป็นการเตรียมความพร้อมและส่งเสริมนิสิต
                            ที่จะเข้ามาประกอบอาชีพนักเทคโนโลยีสารสนเทศ
                            และผู้ประกอบการ
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-share-nodes"></i>
                    </div>
                    <div class="goal-content">
                        <h3>สร้างความสัมพันธ์ระหว่างนิสิต</h3>
                        <p>
                            เพื่อสร้างเสริมความสัมพันธ์ระหว่างนิสิตสาขาคอมพิวเตอร์ธุรกิจด้วยกันเอง
                            และระหว่างนิสิตสาขาเทคโนโลยีสารสนเทศธุรกิจ
                            สาขาธุรกิจดิจิทัลและระบบสารสนเทศ
                            กับนิสิตที่มีความสนใจเกี่ยวกับคอมพิวเตอร์ธุรกิจและเทคโนโลยีสารสนเทศ
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="goal-content">
                        <h3>ส่งเสริมบุคลิกภาพของสมาชิก</h3>
                        <p>
                            เพื่อเป็นการเสริมสร้างให้สมาชิกมีบุคลิกภาพ
                            เป็นนักเทคโนโลยีสารสนเทศ
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-regular fa-clock"></i>
                    </div>
                    <div class="goal-content">
                        <h3>ใช้เวลาว่างให้เกิดประโยชน์</h3>
                        <p>
                            เพื่อให้นิสิตสาขาคอมพิวเตอร์ธุรกิจ
                            และสาขาเทคโนโลยีสารสนเทศธุรกิจ
                            สาขาธุรกิจดิจิทัลและระบบสารสนเทศ
                            ใช้เวลาว่างให้เกิดประโยชน์
                        </p>
                    </div>
                </article>

                <article class="goal-card">
                    <div class="goal-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="goal-content">
                        <h3>ส่งเสริมจรรยาบรรณในวิชาชีพ</h3>
                        <p>
                            เพื่อให้สมาชิกมีจรรยาบรรณในการประกอบอาชีพ
                            นักเทคโนโลยีสารสนเทศ นักธุรกิจ และผู้ประกอบการ
                        </p>
                    </div>
                </article>

            </div>

        </div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>