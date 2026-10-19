<?php
require_once __DIR__ . '/includes/bootstrap.php';
// contact.php
// หน้านี้ใช้สำหรับแสดงข้อมูลติดต่อ แผนที่ และ Social Media ของ Bizcomp Club
// ข้อมูลสามารถแก้ไขได้จากระบบ Admin

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$pageTitle = 'ติดต่อเรา';
$pageCss = 'contact.css';


// =========================================
// ค่าเริ่มต้น
// =========================================

$contact = [
    'club_name' => 'Bizcomp Club',

    'address' =>
        'คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม',

    'phone' => '',

    'email' => '',

    'facebook' =>
        'https://www.facebook.com/profile.php?id=100094575348518',

    'tiktok' =>
        'https://www.tiktok.com/@bizcompclub',

    'google_maps_url' =>
        'https://maps.app.goo.gl/yoUfi96Wze3pY2A38'
];


// =========================================
// ดึงข้อมูลจาก site_settings
// =========================================

try {

    $stmt = $pdo->query("
        SELECT
            setting_key,
            setting_value
        FROM site_settings
    ");

    $settings = $stmt->fetchAll();


    foreach ($settings as $setting) {

        $key =
            strtolower(
                trim(
                    $setting['setting_key']
                )
            );

        $value =
            trim(
                (string)$setting['setting_value']
            );


        if (
            array_key_exists(
                $key,
                $contact
            )
        ) {

            $contact[$key] = $value;
        }

    }

} catch (Throwable $e) {

    // ถ้าอ่าน site_settings ไม่ได้
    // ใช้ค่าเริ่มต้นด้านบน
}


// =========================================
// GOOGLE MAP
// =========================================

// ปุ่มเปิด Google Maps
$mapLink =
    $contact['google_maps_url'];


// แผนที่ภายในหน้าเว็บ
// ใช้ที่อยู่จาก Admin เป็นตำแหน่งค้นหา

$mapEmbed =
    'https://www.google.com/maps?q=' .
    urlencode($contact['address']) .
    '&z=17&output=embed';


include 'includes/header.php';
?>


<!-- =========================================
     HERO
========================================= -->

<section class="contact-hero">

    <div class="contact-hero-overlay"></div>


    <div class="contact-container contact-hero-inner">


        <div class="contact-hero-icon">

            <i class="fa-solid fa-location-dot"></i>

        </div>


        <div class="contact-hero-text">

            <span>
                CONTACT BIZCOMP CLUB
            </span>

            <h1>
                ติดต่อเรา
            </h1>

            <p>
                ติดต่อสอบถามข้อมูลเกี่ยวกับชมรม
                กิจกรรม และการสมัครสมาชิก Bizcomp Club
            </p>

        </div>


    </div>

</section>


<!-- =========================================
     CONTACT
========================================= -->

<section class="contact-section">

    <div class="contact-container">


        <!-- =====================================
             HEADING
        ====================================== -->

        <div class="contact-heading">

            <span>
                GET IN TOUCH
            </span>

            <h2>
                ช่องทางการติดต่อ
            </h2>

            <div class="contact-title-line"></div>

            <p>
                สามารถติดต่อและติดตามข้อมูลของชมรม
                ผ่านช่องทางด้านล่าง
            </p>

        </div>


        <!-- =====================================
             CONTACT CARDS
        ====================================== -->

        <div class="contact-grid">


            <!-- =================================
                 ที่ตั้ง
            ================================== -->

            <article class="contact-card">

                <div class="contact-card-icon">

                    <i class="fa-solid fa-location-dot"></i>

                </div>


                <div>

                    <span>
                        LOCATION
                    </span>

                    <h3>
                        ที่ตั้งชมรม
                    </h3>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $contact['address']
                            )
                        ) ?>
                    </p>

                </div>

            </article>


            <!-- =================================
                 โทรศัพท์
            ================================== -->

            <article class="contact-card">

                <div class="contact-card-icon">

                    <i class="fa-solid fa-phone"></i>

                </div>


                <div>

                    <span>
                        PHONE
                    </span>

                    <h3>
                        เบอร์โทรศัพท์
                    </h3>


                    <?php if (!empty($contact['phone'])): ?>

                        <a
                            href="tel:<?= htmlspecialchars(
                                preg_replace(
                                    '/[^0-9+]/',
                                    '',
                                    $contact['phone']
                                )
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $contact['phone']
                            ) ?>

                        </a>

                    <?php else: ?>

                        <p>
                            ยังไม่ได้ระบุเบอร์โทรศัพท์
                        </p>

                    <?php endif; ?>

                </div>

            </article>


            <!-- =================================
                 EMAIL
            ================================== -->

            <article class="contact-card">

                <div class="contact-card-icon">

                    <i class="fa-regular fa-envelope"></i>

                </div>


                <div>

                    <span>
                        EMAIL
                    </span>

                    <h3>
                        อีเมล
                    </h3>


                    <?php if (!empty($contact['email'])): ?>

                        <a
                            href="mailto:<?= htmlspecialchars(
                                $contact['email']
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $contact['email']
                            ) ?>

                        </a>

                    <?php else: ?>

                        <p>
                            ยังไม่ได้ระบุอีเมล
                        </p>

                    <?php endif; ?>

                </div>

            </article>


        </div>


        <!-- =====================================
             MAP + SOCIAL
        ====================================== -->

        <div class="contact-main-grid">


            <!-- =================================
                 GOOGLE MAP
            ================================== -->

            <div class="contact-map-card">


                <div class="contact-box-heading">

                    <div>

                        <span>
                            OUR LOCATION
                        </span>

                        <h3>
                            แผนที่
                        </h3>

                    </div>


                    <div class="contact-box-icon">

                        <i class="fa-solid fa-map-location-dot"></i>

                    </div>

                </div>


                <!-- =================================
                     MAP
                ================================== -->

                <div class="contact-map">

                    <iframe
                        src="<?= htmlspecialchars($mapEmbed) ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                        title="แผนที่ Bizcomp Club"
                    ></iframe>

                </div>


                <!-- =================================
                     MAP INFO
                ================================== -->

                <div class="map-info">


                    <div class="map-location-icon">

                        <i class="fa-solid fa-location-dot"></i>

                    </div>


                    <div class="map-location-text">

                        <strong>
                            ที่ตั้งชมรม
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $contact['address']
                            ) ?>
                        </span>

                    </div>


                    <?php if (!empty($mapLink)): ?>

                        <a
                            href="<?= htmlspecialchars($mapLink) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="map-button"
                        >

                            <i class="fa-solid fa-map"></i>

                            เปิดใน Google Maps

                            <i class="fa-solid fa-arrow-up-right-from-square"></i>

                        </a>

                    <?php endif; ?>


                </div>

            </div>


            <!-- =================================
                 SOCIAL MEDIA
            ================================== -->

            <div class="contact-social-card">


                <div class="contact-box-heading">

                    <div>

                        <span>
                            FOLLOW US
                        </span>

                        <h3>
                            ติดตามพวกเรา
                        </h3>

                    </div>


                    <div class="contact-box-icon">

                        <i class="fa-solid fa-share-nodes"></i>

                    </div>

                </div>


                <p class="social-description">

                    ไม่พลาดทุกการอัปเดต
                    กิจกรรมสนุก ๆ และความรู้ดี ๆ
                    จาก Bizcomp Club

                </p>


                <div class="social-list">


                    <!-- =============================
                         FACEBOOK
                    ============================== -->

                    <?php if (!empty($contact['facebook'])): ?>

                        <a
                            href="<?= htmlspecialchars(
                                $contact['facebook']
                            ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="social-item"
                        >

                            <div class="social-icon facebook">

                                <i class="fa-brands fa-facebook-f"></i>

                            </div>


                            <div class="social-text">

                                <strong>
                                    Facebook
                                </strong>

                                <span>
                                    Bizcomp Club
                                </span>

                            </div>


                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    <?php endif; ?>


                    <!-- =============================
                         TIKTOK
                    ============================== -->

                    <?php if (!empty($contact['tiktok'])): ?>

                        <a
                            href="<?= htmlspecialchars(
                                $contact['tiktok']
                            ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="social-item"
                        >

                            <div class="social-icon tiktok">

                                <i class="fa-brands fa-tiktok"></i>

                            </div>


                            <div class="social-text">

                                <strong>
                                    TikTok
                                </strong>

                                <span>
                                    @bizcompclub
                                </span>

                            </div>


                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    <?php endif; ?>


                </div>


                <div class="social-note">

                    <i class="fa-solid fa-circle-info"></i>

                    <p>
                        สามารถติดตามข่าวสาร
                        กิจกรรม และการประกาศต่าง ๆ
                        ของชมรมผ่าน Social Media ได้
                    </p>

                </div>


            </div>

        </div>

    </div>

</section>


<?php
include 'includes/footer.php';
?>