<!-- footer.php
     ไฟล์นี้ใช้สำหรับแสดงข้อมูลท้ายเว็บไซต์และช่องทางติดต่อ -->
<!-- ==========================================
     FOOTER
========================================== -->

<footer class="main-footer">

    <div class="footer-container">

        <!-- ================================
             LOGO / ข้อมูลชมรม
        ================================= -->

        <div class="footer-brand">

            <div class="footer-brand-header">

                <img
                    src="assets/images/logo.png"
                    alt="<?= htmlspecialchars($siteName ?? 'Bizcomp Club') ?> Logo"
                    class="footer-logo"
                >

                <div class="footer-brand-text">

                    <h3><?= htmlspecialchars(mb_strtoupper($siteName ?? 'Bizcomp Club', 'UTF-8')) ?></h3>

                    <p>
                        ชมรมบิตคอม
                    </p>

                </div>

            </div>


            <p class="footer-description">
                คณะการบัญชีและการจัดการ<br>
                มหาวิทยาลัยมหาสารคาม
            </p>

        </div>


        <!-- ================================
             เมนู
        ================================= -->

        <div class="footer-column">

            <h4>เมนู</h4>

            <a href="index.php">
                หน้าหลัก
            </a>

            <a href="about.php">
                เกี่ยวกับชมรม
            </a>

            <a href="activities.php">
                กิจกรรม
            </a>

            <a href="contact.php">
                ติดต่อเรา
            </a>

            <a href="privacy.php">นโยบายความเป็นส่วนตัว</a>

        </div>


        <!-- ================================
             ติดต่อ
        ================================= -->

        <div class="footer-column footer-contact">

            <h4>ติดต่อเรา</h4>

            <div class="footer-contact-item">

                <i class="fa-solid fa-location-dot"></i>

                <span>
                    คณะการบัญชีและการจัดการ<br>
                    มหาวิทยาลัยมหาสารคาม
                </span>

            </div>


            <div class="footer-contact-item">

                <i class="fa-solid fa-phone"></i>

                <span>
                    เบอร์โทรศัพท์
                </span>

            </div>

        </div>


        <!-- ================================
             SOCIAL
        ================================= -->

        <div class="footer-column">

            <h4>ติดตามเรา</h4>

            <a
                href="https://www.facebook.com/profile.php?id=100094575348518"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-brands fa-facebook-f"></i>
                Facebook

            </a>


            <a
                href="https://www.tiktok.com/@bizcompclub"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-brands fa-tiktok"></i>
                TikTok

            </a>

        </div>

    </div>


    <!-- ================================
         COPYRIGHT
    ================================= -->

    <div class="footer-bottom">

        <div class="footer-bottom-container">

            <p>
                © <?= date('Y') ?> <?= htmlspecialchars($siteName ?? 'Bizcomp Club') ?>
                | คณะการบัญชีและการจัดการ
                มหาวิทยาลัยมหาสารคาม
            </p>

        </div>

    </div>

</footer>


</body>
</html>
