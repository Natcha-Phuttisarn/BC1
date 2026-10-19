<?php
require_once __DIR__ . '/includes/bootstrap.php';
// user_guide.php
// คู่มือการใช้งานเว็บไซต์ Bizcomp Club จากขั้นตอนที่มีอยู่ในระบบจริง

$pageTitle = 'วิธีการใช้งานระบบ';
$pageCss = 'user_guide.css';

include 'includes/header.php';
?>

<main class="user-guide-page">

    <section class="user-guide-hero">

        <div class="user-guide-container">

            <div class="user-guide-kicker">
                USER GUIDE
            </div>

            <h1>วิธีการใช้งานระบบ</h1>

            <p>
                คู่มือการใช้งานเว็บไซต์ Bizcomp Club
                สำหรับสมาชิกและผู้สนใจ
            </p>

        </div>

    </section>

    <section class="user-guide-content">

        <div class="user-guide-container">

            <div class="guide-section-heading">
                <h2>ขั้นตอนการใช้งานระบบ</h2>
                <p>ทำตามขั้นตอนต่อไปนี้เพื่อสมัครสมาชิกและเข้าร่วมกิจกรรมของชมรม</p>
            </div>

            <div class="guide-steps">

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">1</span>
                        <h3>สมัครสมาชิก / เข้าสู่ระบบ</h3>
                        <p>ผู้ใช้ใหม่สมัครสมาชิกด้วยข้อมูลจริง ส่วนผู้ที่มีบัญชีแล้วสามารถเข้าสู่ระบบด้วยอีเมลและรหัสผ่านเดิม</p>
                    </div>
                </article>

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-regular fa-pen-to-square"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">2</span>
                        <h3>กรอกและตรวจสอบข้อมูล</h3>
                        <p>กรอกข้อมูลส่วนตัว ข้อมูลนิสิต คณะ และสาขาให้ครบถ้วนก่อนดำเนินการในขั้นตอนถัดไป</p>
                    </div>
                </article>

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-regular fa-credit-card"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">3</span>
                        <h3>ชำระค่าสมัครและส่งหลักฐาน</h3>
                        <p>ชำระค่าสมัครตามข้อมูลที่ระบบแสดง และแนบหลักฐานให้ครบก่อนส่งคำขอเพื่อตรวจสอบ</p>
                    </div>
                </article>

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">4</span>
                        <h3>ติดตามสถานะการสมัคร</h3>
                        <p>ตรวจสอบผลการสมัครและผลตรวจหลักฐานได้จากหน้า “สถานะสมาชิก” ภายในเมนูบัญชีของฉัน</p>
                    </div>
                </article>

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-regular fa-calendar-plus"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">5</span>
                        <h3>สมัครเข้าร่วมกิจกรรม</h3>
                        <p>สมาชิกที่ได้รับอนุมัติและยังมีสถานะใช้งานสามารถเปิดรายละเอียดและสมัครกิจกรรมที่เปิดรับได้</p>
                    </div>
                </article>

                <article class="guide-step-card">
                    <div class="guide-step-icon">
                        <i class="fa-regular fa-rectangle-list"></i>
                    </div>
                    <div class="guide-step-copy">
                        <span class="guide-step-number">6</span>
                        <h3>ตรวจสอบกิจกรรมของฉัน</h3>
                        <p>ดูรายการที่ลงทะเบียนและยกเลิกได้เมื่อปุ่มยังใช้งานได้ ตามระยะเวลาและเงื่อนไขที่ระบบกำหนด</p>
                    </div>
                </article>

            </div>

            <div class="guide-section-heading guide-advice-heading">
                <h2>คำแนะนำเพิ่มเติม</h2>
                <p>ข้อมูลสำคัญที่ช่วยให้ใช้งานเว็บไซต์ได้อย่างถูกต้องและปลอดภัย</p>
            </div>

            <div class="guide-advice-grid">

                <article class="guide-advice-card">
                    <div class="guide-advice-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h3>การเก็บรักษาบัญชี</h3>
                        <p>เก็บรหัสผ่านเป็นความลับ และใช้หน้าเปลี่ยนรหัสผ่านเมื่อจำเป็น</p>
                    </div>
                </article>

                <article class="guide-advice-card">
                    <div class="guide-advice-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h3>ตรวจสอบสถานะสมาชิก</h3>
                        <p>สิทธิ์ดูรายชื่อสมาชิกและสมัครกิจกรรมขึ้นอยู่กับสถานะสมาชิกที่ระบบตรวจสอบ</p>
                    </div>
                </article>

                <article class="guide-advice-card">
                    <div class="guide-advice-icon">
                        <i class="fa-regular fa-comments"></i>
                    </div>
                    <div>
                        <h3>การติดต่อผู้ดูแลระบบ</h3>
                        <p>หากพบปัญหาการใช้งาน สามารถส่งข้อความผ่านหน้าติดต่อเราได้</p>
                    </div>
                </article>

            </div>

            <div class="guide-quick-links" aria-label="ลิงก์ใช้งานที่เกี่ยวข้อง">
                <a href="register.php">
                    <i class="fa-solid fa-user-plus"></i>
                    สมัครสมาชิก
                </a>
                <a href="login.php">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    เข้าสู่ระบบ
                </a>
                <a href="activities.php">
                    <i class="fa-regular fa-calendar"></i>
                    ดูกิจกรรม
                </a>
                <a href="contact.php">
                    <i class="fa-regular fa-envelope"></i>
                    ติดต่อเรา
                </a>
            </div>

        </div>

    </section>

</main>

<?php include 'includes/footer.php'; ?>
