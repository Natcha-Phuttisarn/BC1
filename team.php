<?php
require_once __DIR__ . '/includes/bootstrap.php';

// team.php
// หน้านี้ใช้สำหรับแสดงอาจารย์ที่ปรึกษาและคณะกรรมการชมรมจากฐานข้อมูล personnel

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$pageTitle = 'บุคลากรชมรม';
$pageCss = 'team.css';


// =========================================
// ดึงอาจารย์ที่ปรึกษา
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM personnel
    WHERE type = 'advisor'
    AND status = 'active'
    ORDER BY display_order ASC, id ASC
");

$stmt->execute();

$advisors = $stmt->fetchAll();


// =========================================
// ดึงคณะกรรมการชมรม
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM personnel
    WHERE type = 'committee'
    AND status = 'active'
    ORDER BY display_order ASC, id ASC
");

$stmt->execute();

$committees = $stmt->fetchAll();


// =========================================
// จัดการตำแหน่งรูปภาพ
// =========================================

function getPersonImage($image)
{
    if (empty($image)) {
        return 'assets/images/logo.png';
    }

    $image = str_replace('\\', '/', $image);

    if (str_starts_with($image, 'uploads/')) {
        return $image;
    }

    if (str_starts_with($image, 'assets/')) {
        return $image;
    }

    return 'uploads/personnel/' . basename($image);
}


// =========================================
// เลือก Icon ตามตำแหน่ง
// =========================================

function getPositionIcon($position)
{
    // รองประธานฝ่ายกิจกรรม
    if (
        mb_stripos($position, 'รองประธาน') !== false &&
        mb_stripos($position, 'กิจกรรม') !== false
    ) {
        return 'fa-calendar-check';
    }

    // รองประธานฝ่ายบริหาร
    if (
        mb_stripos($position, 'รองประธาน') !== false &&
        mb_stripos($position, 'บริหาร') !== false
    ) {
        return 'fa-user-gear';
    }

    // ประธานชมรม
    if (
        mb_stripos($position, 'ประธาน') !== false &&
        mb_stripos($position, 'รองประธาน') === false
    ) {
        return 'fa-crown';
    }

    // เลขาธิการ / เลขานุการ
    if (
        mb_stripos($position, 'เลขาธิการ') !== false ||
        mb_stripos($position, 'เลขานุการ') !== false
    ) {
        return 'fa-file-pen';
    }

    // เหรัญญิก
    if (
        mb_stripos($position, 'เหรัญญิก') !== false
    ) {
        return 'fa-baht-sign';
    }

    // หัวหน้าฝ่ายกิจกรรม
    if (
        mb_stripos($position, 'หัวหน้าฝ่ายกิจกรรม') !== false
    ) {
        return 'fa-calendar-days';
    }

    // ประชาสัมพันธ์
    if (
        mb_stripos($position, 'ประชาสัมพันธ์') !== false
    ) {
        return 'fa-bullhorn';
    }

    return 'fa-user';
}


include 'includes/header.php';

?>


<!-- =========================================
     HERO
========================================= -->

<section class="team-hero">

    <div class="team-hero-overlay"></div>

    <div class="team-container team-hero-inner">

        <div class="team-hero-icon">

            <i class="fa-solid fa-users"></i>

        </div>


        <div class="team-hero-text">

            <span>
                BIZCOMP CLUB PERSONNEL
            </span>

            <h1>
                บุคลากรชมรม
            </h1>

            <p>
                ทำความรู้จักกับอาจารย์ที่ปรึกษา
                และคณะกรรมการบริหารชมรมบิตคอม
            </p>

        </div>

    </div>

</section>


<!-- =========================================
     อาจารย์ที่ปรึกษา
========================================= -->

<section class="team-section advisor-section">

    <div class="team-container">

        <div class="team-heading">

            <span>
                CLUB ADVISOR
            </span>

            <h2>
                อาจารย์ที่ปรึกษาชมรม
            </h2>

            <div class="team-heading-line"></div>

            <p>
                อาจารย์ผู้ให้คำปรึกษาและดูแลการดำเนินงานของ Bizcomp Club
            </p>

        </div>


        <?php if ($advisors): ?>

            <div class="advisor-list">

                <?php foreach ($advisors as $person): ?>

                    <article class="advisor-card">

                        <div class="advisor-photo">

                            <img
                                src="<?= htmlspecialchars(
                                    getPersonImage($person['image'])
                                ) ?>"
                                alt="<?= htmlspecialchars($person['name']) ?>"
                                onerror="this.src='assets/images/logo.png';"
                            >

                            <div class="person-photo-icon">

                                <i class="fa-solid fa-user-tie"></i>

                            </div>

                        </div>


                        <div class="advisor-info">

                            <span class="person-position">

                                <?= htmlspecialchars(
                                    $person['position']
                                ) ?>

                            </span>


                            <h3>

                                <?= htmlspecialchars(
                                    $person['name']
                                ) ?>

                            </h3>


                            <?php if (!empty($person['department'])): ?>

                                <p class="person-department">

                                    <i class="fa-solid fa-building-columns"></i>

                                    <?= htmlspecialchars(
                                        $person['department']
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <?php if (!empty($person['description'])): ?>

                                <p class="person-description">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $person['description']
                                        )
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <?php if (
                                (!empty($person['phone']) && !empty($person['show_phone'])) ||
                                (!empty($person['email']) && !empty($person['show_email']))
                            ): ?>

                                <div class="person-contact">

                                    <?php if (!empty($person['phone']) && !empty($person['show_phone'])): ?>

                                        <a
                                            href="tel:<?= htmlspecialchars(
                                                preg_replace(
                                                    '/[^0-9+]/',
                                                    '',
                                                    $person['phone']
                                                )
                                            ) ?>"
                                        >

                                            <i class="fa-solid fa-phone"></i>

                                            <?= htmlspecialchars(
                                                $person['phone']
                                            ) ?>

                                        </a>

                                    <?php endif; ?>


                                    <?php if (!empty($person['email']) && !empty($person['show_email'])): ?>

                                        <a
                                            href="mailto:<?= htmlspecialchars(
                                                $person['email']
                                            ) ?>"
                                        >

                                            <i class="fa-regular fa-envelope"></i>

                                            <?= htmlspecialchars(
                                                $person['email']
                                            ) ?>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="team-empty">

                <div class="team-empty-icon">

                    <i class="fa-solid fa-user-tie"></i>

                </div>

                <h3>
                    ยังไม่มีข้อมูลอาจารย์ที่ปรึกษา
                </h3>

                <p>
                    ข้อมูลจะแสดงเมื่อผู้ดูแลระบบเพิ่มข้อมูลอาจารย์ที่ปรึกษา
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =========================================
     คณะกรรมการชมรม
========================================= -->

<section class="team-section committee-section">

    <div class="team-container">

        <div class="team-heading">

            <span>
                CLUB COMMITTEE
            </span>

            <h2>
                คณะกรรมการบริหารชมรม
            </h2>

            <div class="team-heading-line"></div>

            <p>
                ผู้มีส่วนร่วมในการบริหารและดำเนินกิจกรรมของ Bizcomp Club
            </p>

        </div>


        <?php if ($committees): ?>

            <div class="committee-grid">

                <?php foreach ($committees as $person): ?>

                    <article class="committee-card">

                        <div class="committee-photo">

                            <img
                                src="<?= htmlspecialchars(
                                    getPersonImage($person['image'])
                                ) ?>"
                                alt="<?= htmlspecialchars($person['name']) ?>"
                                onerror="this.src='assets/images/logo.png';"
                            >


                            <div class="committee-icon">

                                <i
                                    class="fa-solid <?= htmlspecialchars(
                                        getPositionIcon(
                                            $person['position']
                                        )
                                    ) ?>"
                                ></i>

                            </div>

                        </div>


                        <div class="committee-info">

                            <span class="person-position">

                                <?= htmlspecialchars(
                                    $person['position']
                                ) ?>

                            </span>


                            <h3>

                                <?= htmlspecialchars(
                                    $person['name']
                                ) ?>

                            </h3>


                            <?php if (!empty($person['department'])): ?>

                                <p class="person-department center">

                                    <?= htmlspecialchars(
                                        $person['department']
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <?php if (!empty($person['description'])): ?>

                                <p class="person-description center">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $person['description']
                                        )
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <?php if (
                                (!empty($person['phone']) && !empty($person['show_phone'])) ||
                                (!empty($person['email']) && !empty($person['show_email']))
                            ): ?>

                                <div class="person-contact center">

                                    <?php if (!empty($person['phone']) && !empty($person['show_phone'])): ?>

                                        <a
                                            href="tel:<?= htmlspecialchars(
                                                preg_replace(
                                                    '/[^0-9+]/',
                                                    '',
                                                    $person['phone']
                                                )
                                            ) ?>"
                                        >

                                            <i class="fa-solid fa-phone"></i>

                                            <?= htmlspecialchars(
                                                $person['phone']
                                            ) ?>

                                        </a>

                                    <?php endif; ?>


                                    <?php if (!empty($person['email']) && !empty($person['show_email'])): ?>

                                        <a
                                            href="mailto:<?= htmlspecialchars(
                                                $person['email']
                                            ) ?>"
                                        >

                                            <i class="fa-regular fa-envelope"></i>

                                            <?= htmlspecialchars(
                                                $person['email']
                                            ) ?>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="team-empty">

                <div class="team-empty-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

                <h3>
                    ยังไม่มีข้อมูลคณะกรรมการชมรม
                </h3>

                <p>
                    ผู้ดูแลระบบสามารถเพิ่มข้อมูลคณะกรรมการได้จากหน้า Admin
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =========================================
     NAVIGATION
========================================= -->

<section class="team-bottom">

    <div class="team-container team-bottom-inner">

        <a href="goals.php">

            <i class="fa-solid fa-arrow-left"></i>

            วัตถุประสงค์

        </a>


        <a href="activities.php">

            ดูกิจกรรม

            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>


<?php include 'includes/footer.php'; ?>
