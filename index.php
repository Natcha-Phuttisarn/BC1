<?php
require_once __DIR__ . '/includes/bootstrap.php';
// index.php หน้านี้ใช้สำหรับแสดงหน้าหลักและกิจกรรมล่าสุดของ Bizcomp Club

$pageTitle = "หน้าหลัก";
$pageCss = "index.css";

require_once 'includes/db.php';
require_once 'includes/functions.php';

date_default_timezone_set(
    'Asia/Bangkok'
);


$latestActivities = [];
$hasNewActivities = false;
$latestGallery = [];


try {

    $sql = "
        SELECT
            a.*,

            (
                SELECT COUNT(*)

                FROM activity_registrations ar

                WHERE ar.activity_id = a.id

                AND ar.registration_status =
                    'registered'

            ) AS registered_count

        FROM activities a

        WHERE a.publish_status =
            'published'

        ORDER BY

            COALESCE(
                a.published_at,
                a.created_at
            ) DESC

        LIMIT 3
    ";


    $stmt = $pdo->query($sql);

    $latestActivities =
        $stmt->fetchAll();

    foreach ($latestActivities as $latestActivity) {

        if (isActivityNew($latestActivity)) {
            $hasNewActivities = true;
            break;
        }
    }


} catch (PDOException $e) {

    $latestActivities = [];

}


try {

    $galleryStmt = $pdo->query("
        SELECT g.*
        FROM activity_gallery g
        WHERE g.status = 'active'
        ORDER BY
            g.created_at DESC,
            g.id DESC
        LIMIT 6
    ");

    $latestGallery = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $latestGallery = [];

}


include 'includes/header.php';

?>

<!-- โหลด index.css ซ้ำแบบ cache-busting เพื่อให้เบราว์เซอร์ใช้ไฟล์ล่าสุดแน่นอน -->
<link
    rel="stylesheet"
    href="assets/css/index.css?v=20260917"
>

<link
    rel="stylesheet"
    href="assets/css/gallery-home.css"
>

<main class="home-page">


    <!-- HERO -->

    <section class="home-hero">

        <div class="home-hero-overlay"></div>


        <div class="home-hero-container">
            <div class="home-hero-content">
                <div class="hero-accent" aria-hidden="true"></div>

                <h1 class="hero-title">
                    BIZCOMP CLUB
                </h1>
                <h2 class="hero-subtitle">
                    คณะการบัญชีและการจัดการ
                    <br>
                    มหาวิทยาลัยมหาสารคาม
                </h2>
                <p class="hero-description">
                    แหล่งรวมตัวของนิสิตที่สนใจ
                    ด้านเทคโนโลยีสารสนเทศ
                    พัฒนาทักษะ สร้างสรรค์นวัตกรรม
                    และทำกิจกรรมร่วมกัน
                </p>

                <div class="hero-actions">
                    <a
                        href="activities.php"
                        class="hero-btn hero-btn-primary"
                    >
                        ดูรายละเอียดกิจกรรม
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    
                    <a
                        href="about.php"
                        class="hero-btn hero-btn-outline"
                    >

                        <i class="fa-regular fa-file-lines"></i>

                        เกี่ยวกับชมรม

                    </a>


                </div>



                <div class="hero-social">


                    <div class="social-heading">
                        ติดตามพวกเรา
                    </div>


                    <div class="social-list">


                        <a
                            href="https://www.facebook.com/profile.php?id=100094575348518"
                            target="_blank"
                            class="social-card"
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

                        </a>



                        <a
                            href="https://www.tiktok.com/@bizcompclub"
                            target="_blank"
                            class="social-card"
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

                        </a>


                    </div>

                </div>


            </div>

        </div>

    </section>



    <!-- กิจกรรมล่าสุด -->

    <section class="latest-section">

        <div class="latest-container">


            <div class="latest-header">


                <div>

                    <div class="latest-label">
                        ACTIVITIES
                    </div>

                    <div class="latest-title-row">

                        <h2 class="latest-title">
                            กิจกรรมล่าสุด
                        </h2>

                        <?php if ($hasNewActivities): ?>

                            <span class="latest-new-label">
                                NEW
                            </span>

                        <?php endif; ?>

                        <p class="latest-subtext">
                            ติดตามกิจกรรมใหม่ ๆ จากชมรม Bizcomp Club
                        </p>

                    </div>

                </div>


                <a
                    href="activities.php"
                    class="all-activities-btn"
                >

                    ดูกิจกรรมทั้งหมด

                    <i class="fa-solid fa-chevron-right"></i>

                </a>


            </div>



            <div class="activity-grid">


                <?php if (!empty($latestActivities)): ?>


                    <?php foreach ($latestActivities as $index => $activity): ?>


                        <?php

                        $registeredCount =
                            (int)
                            ($activity['registered_count'] ?? 0);


                        $isNew =
                            isActivityNew($activity);


                        $maxParticipants =
                            !empty(
                                $activity['max_participants']
                            )
                            ? (int)
                                $activity['max_participants']
                            : null;


                        $now = new DateTime();


                        $registrationOpen =
                            new DateTime(
                                $activity['registration_open']
                            );


                        $registrationClose =
                            new DateTime(
                                $activity['registration_close']
                            );


                        if ($now < $registrationOpen) {

                            $registrationStatus =
                                "ยังไม่เปิดรับสมัคร";

                            $statusClass =
                                "waiting";

                        } elseif (
                            $now > $registrationClose
                        ) {

                            $registrationStatus =
                                "ปิดรับสมัคร";

                            $statusClass =
                                "closed";

                        } elseif (
                            $maxParticipants !== null
                            &&
                            $registeredCount >=
                            $maxParticipants
                        ) {

                            $registrationStatus =
                                "เต็ม";

                            $statusClass =
                                "full";

                        } else {

                            $registrationStatus =
                                "เปิดรับสมัคร";

                            $statusClass =
                                "open";

                        }



                        if (
                            !empty(
                                $activity['cover_image']
                            )
                        ) {

                            $coverImage =
                                'uploads/activities/covers/'
                                .
                                $activity['cover_image'];

                        } else {

                            $coverImage =
                                'assets/images/logo.png';

                        }


                        $activityDate =
                            date(
                                'd/m/Y',
                                strtotime(
                                    $activity['activity_date']
                                )
                            );

                        ?>


                        <a
                            href="activities.php?id=<?= (int) $activity['id'] ?>"
                            class="activity-card <?= $isNew ? 'latest-card' : '' ?>"
                        >


                            <div class="activity-card-image">


                                <img
                                    src="<?= htmlspecialchars($coverImage) ?>"
                                    alt="<?= htmlspecialchars($activity['title']) ?>"
                                >


                                <span
                                    class="activity-badge <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars($registrationStatus) ?>

                                </span>

                                <?php if ($isNew): ?>

                                    <span class="activity-new-badge">
                                        NEW
                                    </span>

                                <?php endif; ?>


                            </div>



                            <div class="activity-card-body">


                                <div class="activity-meta">


                                    <span>

                                        <i class="fa-regular fa-calendar"></i>

                                        <?= $activityDate ?>

                                    </span>



                                    <?php if (
                                        !empty(
                                            $activity['location']
                                        )
                                    ): ?>

                                        <span>

                                            <i class="fa-solid fa-location-dot"></i>

                                            <?= htmlspecialchars(
                                                $activity['location']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>



                                    <?php if (
                                        $maxParticipants !== null
                                    ): ?>

                                        <span>

                                            <i class="fa-solid fa-users"></i>

                                            <?= $registeredCount ?>

                                            /

                                            <?= $maxParticipants ?>

                                            คน

                                        </span>

                                    <?php endif; ?>


                                </div>



                                <h3 class="activity-title">

                                    <?= htmlspecialchars(
                                        $activity['title']
                                    ) ?>

                                </h3>



                                <p class="activity-description">

                                    <?= htmlspecialchars(
                                        $activity['short_description']
                                        ?: 'ดูรายละเอียดกิจกรรมเพิ่มเติม'
                                    ) ?>

                                </p>



                                <div class="activity-card-footer">


                                    <span class="activity-more">
                                        ดูรายละเอียด
                                    </span>


                                    <span class="activity-arrow">

                                        <i class="fa-solid fa-arrow-right"></i>

                                    </span>


                                </div>


                            </div>


                        </a>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="activity-empty">


                        <div class="empty-icon">

                            <i class="fa-regular fa-calendar"></i>

                        </div>


                        <h3>
                            ยังไม่มีกิจกรรม
                        </h3>


                        <p>
                            เมื่อผู้ดูแลระบบเผยแพร่กิจกรรมใหม่
                            กิจกรรมล่าสุดจะแสดงที่นี่โดยอัตโนมัติ
                        </p>


                    </div>


                <?php endif; ?>


            </div>


        </div>

    </section>


    <?php if (!empty($latestGallery)): ?>

        <!-- ภาพกิจกรรมที่ผ่านมา -->

        <section class="gallery-section">

            <div class="gallery-container">

                <div class="gallery-header">

                    <div class="gallery-heading">
                        <div class="gallery-label">GALLERY</div>
                        <h2 class="gallery-title">ภาพกิจกรรมที่ผ่านมา</h2>
                        <p class="gallery-subtext">
                            รวบรวมภาพบรรยากาศจากกิจกรรมต่าง ๆ ของชมรมบิตคอม
                        </p>
                    </div>

                    <a href="gallery.php" class="gallery-all-btn">
                        ดูทั้งหมด
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </div>

                <div class="gallery-grid">

                    <?php foreach ($latestGallery as $gallery): ?>

                        <?php
                        $galleryImage = 'uploads/gallery/' . rawurlencode(
                            basename((string)$gallery['cover_image'])
                        );
                        ?>

                        <a
                            href="gallery_detail.php?id=<?= (int)$gallery['id'] ?>"
                            class="gallery-card"
                        >
                            <div class="gallery-image">
                                <img
                                    src="<?= htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                >
                            </div>

                            <div class="gallery-info">
                                <?php if (!empty($gallery['activity_date'])): ?>
                                    <div class="gallery-date">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= htmlspecialchars(
                                            formatThaiDate($gallery['activity_date']),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                <?php endif; ?>

                                <h3><?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?></h3>

                                <?php if (!empty($gallery['description'])): ?>
                                    <p><?= htmlspecialchars($gallery['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>

    <?php endif; ?>


</main>


<?php
include 'includes/footer.php';
?>
