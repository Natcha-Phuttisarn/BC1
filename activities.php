<?php
require_once __DIR__ . '/includes/bootstrap.php';
// activities.php
// หน้านี้ใช้สำหรับแสดงกิจกรรม รายละเอียดกิจกรรม
// และกิจกรรมที่ผู้ใช้งานลงทะเบียนไว้

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

date_default_timezone_set('Asia/Bangkok');

$pageTitle = 'กิจกรรม';
$pageCss = 'activities.css?v=20261017-ui-fix1';


// =========================================
// ข้อความแจ้งเตือน
// =========================================

$activitySuccess =
    $_SESSION['activity_success'] ?? '';

$activityError =
    $_SESSION['activity_error'] ?? '';

unset(
    $_SESSION['activity_success'],
    $_SESSION['activity_error']
);


// =========================================
// CSRF สำหรับยกเลิกกิจกรรม
// =========================================

if (empty($_SESSION['activity_csrf'])) {
    $_SESSION['activity_csrf'] =
        bin2hex(random_bytes(32));
}

$activityCsrf =
    $_SESSION['activity_csrf'];


// =========================================
// ฟังก์ชัน Escape
// =========================================

function activityE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================
// รูปกิจกรรม
// =========================================

function getActivityCover(
    array $activity
): string {

    if (!empty($activity['cover_image'])) {

        return
            'uploads/activities/covers/' .
            $activity['cover_image'];
    }

    return 'assets/images/logo.png';
}


// =========================================
// วันที่แบบไทย
// =========================================

function thaiActivityDate(
    ?string $date
): string {

    if (empty($date)) {
        return '-';
    }

    $timestamp =
        strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    $day =
        (int)date('d', $timestamp);

    $month =
        $months[(int)date('m', $timestamp)];

    $year =
        (int)date('Y', $timestamp) + 543;

    return
        $day . ' ' .
        $month . ' ' .
        $year;
}


// =========================================
// แสดงเวลา
// =========================================

function activityTime(
    ?string $start,
    ?string $end
): string {

    if (empty($start)) {
        return '-';
    }

    $text =
        date(
            'H:i',
            strtotime($start)
        ) . ' น.';

    if (!empty($end)) {

        $text .=
            ' - ' .
            date(
                'H:i',
                strtotime($end)
            ) .
            ' น.';
    }

    return $text;
}


// =========================================
// ตรวจสถานะรับสมัครอัตโนมัติ
// =========================================

function getActivityStatus(
    array $activity,
    int $registeredCount
): array {

    $now =
        new DateTime();

    $open =
        !empty($activity['registration_open'])
        ? new DateTime(
            $activity['registration_open']
        )
        : null;

    $close =
        !empty($activity['registration_close'])
        ? new DateTime(
            $activity['registration_close']
        )
        : null;

    $max =
        !empty($activity['max_participants'])
        ? (int)$activity['max_participants']
        : null;


    // ยังไม่เปิด
    if (
        $open !== null &&
        $now < $open
    ) {

        return [
            'text' => 'ยังไม่เปิดรับสมัคร',
            'class' => 'waiting',
            'can_register' => false
        ];
    }


    // หมดเขต
    if (
        $close !== null &&
        $now >= $close
    ) {

        return [
            'text' => 'หมดเขตรับสมัคร',
            'class' => 'closed',
            'can_register' => false
        ];
    }


    // เต็ม
    if (
        $max !== null &&
        $registeredCount >= $max
    ) {

        return [
            'text' => 'ผู้สมัครเต็มแล้ว',
            'class' => 'full',
            'can_register' => false
        ];
    }


    return [
        'text' => 'เปิดรับสมัคร',
        'class' => 'open',
        'can_register' => true
    ];
}


// =========================================
// ตรวจว่ากิจกรรมเริ่มแล้วหรือยัง
// =========================================

function activityHasStarted(
    array $activity
): bool {

    if (
        empty($activity['activity_date'])
    ) {
        return false;
    }

    $time =
        !empty($activity['start_time'])
        ? $activity['start_time']
        : '00:00:00';

    try {

        $start =
            new DateTime(
                $activity['activity_date'] .
                ' ' .
                $time
            );

        return
            new DateTime() >= $start;

    } catch (Throwable $e) {

        return false;
    }
}


// =========================================
// สิทธิ์ยกเลิกภายใน 48 ชั่วโมงหลังสมัคร
// =========================================

function activityCancellationInfo(?string $registeredAt): array
{
    if (empty($registeredAt)) {
        return [
            'can_cancel' => false,
            'deadline' => null
        ];
    }

    try {
        $deadline = new DateTime($registeredAt);
        $deadline->modify('+48 hours');

        return [
            'can_cancel' => new DateTime() < $deadline,
            'deadline' => $deadline
        ];
    } catch (Throwable $e) {
        return [
            'can_cancel' => false,
            'deadline' => null
        ];
    }
}


// =========================================
// Mode
// =========================================

$myMode =
    isset($_GET['my']) &&
    $_GET['my'] === '1';

$activityId =
    isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

// ตรวจว่าเป็นผู้ดูแลระบบหรือไม่
$isAdmin =
    (($_SESSION['role'] ?? '') === 'admin');


// =========================================
// ตรวจสิทธิ์สมาชิกสำหรับการสมัครกิจกรรม
// ใช้คำขอล่าสุดเพื่อไม่ให้สถานะอนุมัติเก่าให้สิทธิ์ผิดพลาด
// =========================================

$canRegisterAsMember = false;

if (isset($_SESSION['user_id']) && !$isAdmin) {
    $memberAccessStmt = $pdo->prepare("
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

    $memberAccessStmt->execute([
        (int)$_SESSION['user_id']
    ]);

    $memberAccess =
        $memberAccessStmt->fetch(PDO::FETCH_ASSOC);

    if (
        $memberAccess &&
        !empty($memberAccess['id']) &&
        ($memberAccess['account_status'] ?? '') === 'active' &&
        ($memberAccess['member_status'] ?? '') === 'active' &&
        ($memberAccess['latest_application_status'] ?? '') === 'approved'
    ) {
        $automaticMemberStatus =
            getAutomaticMemberStatus($memberAccess);

        $canRegisterAsMember =
            ($automaticMemberStatus['code'] ?? '') === 'active';
    }
}


// =========================================================
// กิจกรรมของฉัน
// =========================================================

if ($myMode) {


    // ต้อง Login
    if (!isset($_SESSION['user_id'])) {

        header('Location: login.php');
        exit;
    }


    $userId =
        (int)$_SESSION['user_id'];


    // =========================================
    // ดึงกิจกรรมที่สมัคร
    // =========================================

    $stmt = $pdo->prepare("
        SELECT
            ar.id AS registration_id,
            ar.registration_status,
            ar.registered_at,

            a.*

        FROM activity_registrations ar

        INNER JOIN activities a
            ON a.id = ar.activity_id

        WHERE ar.user_id = ?

        AND ar.registration_status =
            'registered'

        ORDER BY
            CASE
                WHEN TIMESTAMP(a.activity_date, a.end_time) >= NOW()
                THEN 0
                ELSE 1
            END ASC,

            CASE
                WHEN TIMESTAMP(a.activity_date, a.end_time) >= NOW()
                THEN TIMESTAMP(a.activity_date, a.start_time)
            END ASC,

            CASE
                WHEN TIMESTAMP(a.activity_date, a.end_time) >= NOW()
                THEN a.created_at
            END DESC,

            CASE
                WHEN TIMESTAMP(a.activity_date, a.end_time) < NOW()
                THEN TIMESTAMP(a.activity_date, a.end_time)
            END DESC,

            CASE
                WHEN TIMESTAMP(a.activity_date, a.end_time) < NOW()
                THEN a.created_at
            END DESC
    ");

    $stmt->execute([
        $userId
    ]);

    $myActivities =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $pageTitle =
        'กิจกรรมของฉัน';

    include 'includes/header.php';

    ?>


    <main class="activities-page my-activities-page">


        <!-- =================================
             HERO
        ================================== -->

        <section class="activities-hero my-activities-hero">

            <div class="activities-container">

                <div class="my-activities-hero-layout">

                    <div class="my-activities-hero-copy">

                        <div class="activities-label">
                            MY ACTIVITIES
                        </div>

                        <h1 class="activities-heading">
                            กิจกรรมของฉัน
                        </h1>

                        <p class="activities-intro">
                            ตรวจสอบกิจกรรมที่คุณลงทะเบียนเข้าร่วม
                            และสามารถยกเลิกการสมัครก่อนกิจกรรมเริ่มได้
                        </p>

                    </div>

                    <div class="my-activities-visual" aria-hidden="true">

                        <div class="my-calendar-art">
                            <span class="my-calendar-ring ring-left"></span>
                            <span class="my-calendar-ring ring-right"></span>

                            <div class="my-calendar-bar"></div>

                            <div class="my-calendar-grid">
                                <span></span><span></span><span></span>
                                <span></span><span class="active"></span><span></span>
                                <span></span><span></span><span></span>
                            </div>
                        </div>

                        <div class="my-calendar-badge">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>

                        <div class="my-calendar-note">
                            <span class="note-dot"></span>
                            <span class="note-lines"></span>
                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================
             รายการ
        ================================== -->

        <section class="my-activities-section">

            <div class="activities-container">


                <!-- ข้อความสำเร็จ -->

                <?php if ($activitySuccess): ?>

                    <div class="my-activity-alert success">

                        <i class="fa-solid fa-circle-check"></i>

                        <?= activityE($activitySuccess) ?>

                    </div>

                <?php endif; ?>


                <!-- Error -->

                <?php if ($activityError): ?>

                    <div class="my-activity-alert error">

                        <i class="fa-solid fa-circle-exclamation"></i>

                        <?= activityE($activityError) ?>

                    </div>

                <?php endif; ?>


                <!-- ปุ่มกลับ -->

                <div class="my-activities-top">

                    <a
                        href="activities.php"
                        class="my-back-btn"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        ดูกิจกรรมทั้งหมด

                    </a>

                </div>


                <?php if (!empty($myActivities)): ?>


                    <div class="my-activities-grid">


                        <?php foreach (
                            $myActivities
                            as $activity
                        ): ?>


                            <?php

                            $cover =
                                getActivityCover(
                                    $activity
                                );

                            $isNew =
                                isActivityNew($activity);

                            $started =
                                activityHasStarted(
                                    $activity
                                );

                            $cancellationInfo =
                                activityCancellationInfo(
                                    $activity['registered_at'] ?? null
                                );

                            $canCancel =
                                !$started &&
                                $cancellationInfo['can_cancel'];

                            ?>


                            <article class="my-activity-card">


                                <!-- รูป -->

                                <a
                                    href="activities.php?id=<?= (int)$activity['id'] ?>"
                                    class="my-activity-cover"
                                >

                                    <img
                                        src="<?= activityE($cover) ?>"
                                        alt="<?= activityE($activity['title']) ?>"
                                    >

                                    <?php if ($started): ?>

                                        <span class="my-activity-state finished">

                                            <i class="fa-solid fa-clock-rotate-left"></i>

                                            เริ่มกิจกรรมแล้ว

                                        </span>

                                    <?php else: ?>

                                        <span class="my-activity-state registered">

                                            <i class="fa-solid fa-circle-check"></i>

                                            ลงทะเบียนแล้ว

                                        </span>

                                    <?php endif; ?>

                                    <?php if ($isNew): ?>

                                        <span class="activity-new-badge">
                                            NEW
                                        </span>

                                    <?php endif; ?>

                                </a>


                                <!-- เนื้อหา -->

                                <div class="my-activity-content">


                                    <h2>

                                        <?= activityE(
                                            $activity['title']
                                        ) ?>

                                    </h2>


                                    <?php if (
                                        !empty(
                                            $activity['short_description']
                                        )
                                    ): ?>

                                        <p class="my-activity-desc">

                                            <?= activityE(
                                                $activity['short_description']
                                            ) ?>

                                        </p>

                                    <?php endif; ?>


                                    <div class="my-activity-info">


                                        <div>

                                            <i class="fa-regular fa-calendar"></i>

                                            <span>

                                                <?= activityE(
                                                    thaiActivityDate(
                                                        $activity['activity_date']
                                                    )
                                                ) ?>

                                            </span>

                                        </div>


                                        <div>

                                            <i class="fa-regular fa-clock"></i>

                                            <span>

                                                <?= activityE(
                                                    activityTime(
                                                        $activity['start_time'],
                                                        $activity['end_time']
                                                    )
                                                ) ?>

                                            </span>

                                        </div>


                                        <div>

                                            <i class="fa-solid fa-location-dot"></i>

                                            <span>

                                                <?= activityE(
                                                    $activity['location']
                                                    ?: '-'
                                                ) ?>

                                            </span>

                                        </div>


                                    </div>


                                    <div class="my-activity-status-line">

                                        <i class="fa-solid fa-circle-check"></i>

                                        ลงทะเบียนเข้าร่วมแล้ว

                                    </div>

                                    <?php if ($cancellationInfo['deadline']): ?>
                                        <div class="activity-cancel-deadline <?= $canCancel ? '' : 'expired' ?>">
                                            <i class="fa-regular fa-clock"></i>
                                            <?php if ($canCancel): ?>
                                                ยกเลิกได้ถึง <?= activityE($cancellationInfo['deadline']->format('d/m/Y H:i')) ?> น.
                                            <?php else: ?>
                                                พ้นกำหนดยกเลิก 48 ชั่วโมงแล้ว
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>


                                    <div class="my-activity-actions">


                                        <!-- ดูรายละเอียด -->

                                        <a
                                            href="activities.php?id=<?= (int)$activity['id'] ?>"
                                            class="my-detail-btn"
                                        >

                                            <i class="fa-regular fa-eye"></i>

                                            ดูรายละเอียด

                                        </a>


                                        <!-- ยกเลิก -->

                                        <?php if ($canCancel): ?>


                                            <button
                                                type="button"
                                                class="my-cancel-btn"
                                                data-registration-id="<?= (int)$activity['registration_id'] ?>"
                                                data-activity-title="<?= activityE($activity['title']) ?>"
                                                data-activity-date="<?= activityE(thaiActivityDate($activity['activity_date'])) ?>"
                                                onclick="openActivityCancelModal(this)"
                                            >

                                                <i class="fa-solid fa-xmark"></i>

                                                ยกเลิกการสมัคร

                                            </button>


                                        <?php else: ?>


                                            <button
                                                type="button"
                                                class="my-cancel-btn disabled"
                                                disabled
                                            >

                                                <i class="fa-solid fa-lock"></i>

                                                ยกเลิกไม่ได้

                                            </button>


                                        <?php endif; ?>


                                    </div>


                                </div>


                            </article>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="my-empty-card">

                        <div class="my-empty-icon">

                            <i class="fa-regular fa-calendar"></i>

                        </div>

                        <h2>
                            ยังไม่มีกิจกรรมที่ลงทะเบียน
                        </h2>

                        <p>
                            เมื่อคุณสมัครเข้าร่วมกิจกรรม
                            รายการกิจกรรมจะปรากฏในหน้านี้
                        </p>

                        <a
                            href="activities.php"
                            class="my-find-btn"
                        >

                            <i class="fa-solid fa-magnifying-glass"></i>

                            ดูกิจกรรมที่เปิดรับสมัคร

                        </a>

                    </div>


                <?php endif; ?>


            </div>

        </section>


    </main>


    <!-- =========================================
         MODAL ยกเลิกการสมัครกิจกรรม
    ========================================== -->

    <div
        class="activity-cancel-overlay"
        id="activityCancelModal"
        aria-hidden="true"
    >
        <div
            class="activity-cancel-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="activityCancelTitle"
        >

            <div class="activity-cancel-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h2 id="activityCancelTitle">
                ยืนยันการยกเลิกกิจกรรม
            </h2>

            <p class="activity-cancel-description">
                คุณต้องการยกเลิกการลงทะเบียนกิจกรรม
                <strong id="activityCancelName"></strong>
                ใช่หรือไม่
            </p>

            <div class="activity-cancel-summary">
                <span>วันที่จัดกิจกรรม</span>
                <strong id="activityCancelDate">-</strong>
            </div>

            <p class="activity-cancel-warning">
                ยกเลิกได้ภายใน 48 ชั่วโมงหลังสมัครเท่านั้น และเมื่อยกเลิกแล้วจะไม่สามารถสมัครกิจกรรมนี้ซ้ำได้
            </p>

            <form
                action="activity_cancel.php"
                method="POST"
                id="activityCancelForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= activityE($activityCsrf) ?>"
                >

                <input
                    type="hidden"
                    name="registration_id"
                    id="activityCancelRegistrationId"
                    value=""
                >

                <div class="activity-cancel-field">

                    <label for="activityCancelReason">
                        สาเหตุการยกเลิก
                    </label>

                    <textarea
                        name="cancel_reason"
                        id="activityCancelReason"
                        rows="4"
                        maxlength="500"
                        placeholder="ระบุสาเหตุ"
                        required
                    ></textarea>

                    <div class="activity-cancel-counter">
                        <span id="activityCancelCount">0</span>/500
                    </div>

                </div>

                <div class="activity-cancel-actions">

                    <button
                        type="button"
                        class="activity-cancel-back"
                        onclick="closeActivityCancelModal()"
                    >
                        ย้อนกลับ
                    </button>

                    <button
                        type="submit"
                        class="activity-cancel-confirm"
                    >
                        ยืนยันการยกเลิก
                    </button>

                </div>

            </form>

        </div>
    </div>

    <style>
    .activity-cancel-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(17, 28, 45, .58);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .22s ease, visibility .22s ease;
    }

    .activity-cancel-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .activity-cancel-modal {
        width: min(100%, 500px);
        padding: 29px 28px 23px;
        background: #ffffff;
        border: 1px solid #e4e8ef;
        border-radius: 15px;
        box-shadow: 0 24px 70px rgba(12, 31, 58, .25);
        transform: translateY(14px) scale(.985);
        transition: transform .22s ease;
    }

    .activity-cancel-overlay.show .activity-cancel-modal {
        transform: translateY(0) scale(1);
    }

    .activity-cancel-icon {
        width: 58px;
        height: 58px;
        margin: 0 auto 14px;
        display: grid;
        place-items: center;
        color: #db2d32;
        background: #fde9ea;
        border-radius: 50%;
        font-size: 25px;
    }

    .activity-cancel-modal h2 {
        margin: 0;
        color: #18345e;
        text-align: center;
        font-size: 21px;
        font-weight: 700;
    }

    .activity-cancel-description {
        margin: 8px 0 24px;
        color: #718096;
        text-align: center;
        font-size: 12px;
        line-height: 1.7;
    }

    .activity-cancel-description strong {
        display: block;
        margin: 6px 0;
        color: #17365f;
        font-size: 15px;
    }

    .activity-cancel-summary {
        margin-bottom: 12px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        background: #f6f8fb;
        border: 1px solid #e1e7ef;
        border-radius: 9px;
    }

    .activity-cancel-summary span {
        color: #77869a;
        font-size: 11px;
    }

    .activity-cancel-summary strong {
        color: #294866;
        text-align: right;
        font-size: 12px;
    }

    .activity-cancel-warning {
        margin: 0 0 18px;
        color: #a15a17;
        font-size: 10px;
        line-height: 1.65;
    }

    .activity-cancel-field {
        position: relative;
    }

    .activity-cancel-field label {
        display: block;
        margin-bottom: 8px;
        color: #18345e;
        font-size: 13px;
        font-weight: 600;
    }

    .activity-cancel-field textarea {
        width: 100%;
        min-height: 105px;
        padding: 13px 14px 27px;
        resize: vertical;
        box-sizing: border-box;
        color: #334155;
        background: #fbfcfe;
        border: 1px solid #dbe2eb;
        border-radius: 9px;
        outline: none;
        font-family: "Prompt", sans-serif;
        font-size: 13px;
        line-height: 1.65;
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .activity-cancel-field textarea:focus {
        background: #ffffff;
        border-color: #2e6cca;
        box-shadow: 0 0 0 3px rgba(46, 108, 202, .09);
    }

    .activity-cancel-counter {
        position: absolute;
        right: 11px;
        bottom: 8px;
        color: #98a2b3;
        font-size: 10px;
    }

    .activity-cancel-actions {
        margin-top: 23px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .activity-cancel-back,
    .activity-cancel-confirm,
    .activity-register-confirm {
        min-height: 40px;
        padding: 9px 17px;
        border-radius: 8px;
        font-family: "Prompt", sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: .18s ease;
    }

    .activity-cancel-back {
        color: #18345e;
        background: #ffffff;
        border: 1px solid #d7dee8;
    }

    .activity-cancel-back:hover {
        background: #f6f8fb;
    }

    .activity-cancel-confirm {
        color: #ffffff;
        background: #e1252b;
        border: 1px solid #e1252b;
    }

    .activity-cancel-confirm:hover {
        background: #c91f25;
        border-color: #c91f25;
    }

    @media (max-width: 520px) {
        .activity-cancel-modal {
            padding: 24px 18px 19px;
        }

        .activity-cancel-actions {
            flex-direction: column-reverse;
        }

        .activity-cancel-back,
        .activity-cancel-confirm {
            width: 100%;
        }
    }
    </style>

    <script>
    function openActivityCancelModal(trigger) {
        const modal =
            document.getElementById('activityCancelModal');

        const idInput =
            document.getElementById('activityCancelRegistrationId');

        const reason =
            document.getElementById('activityCancelReason');

        const count =
            document.getElementById('activityCancelCount');

        const activityName =
            document.getElementById('activityCancelName');

        const activityDate =
            document.getElementById('activityCancelDate');

        idInput.value = trigger.dataset.registrationId;
        activityName.textContent = '“' + trigger.dataset.activityTitle + '”';
        activityDate.textContent = trigger.dataset.activityDate || '-';
        reason.value = '';
        count.textContent = '0';

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            reason.focus();
        }, 120);
    }

    function closeActivityCancelModal() {
        const modal =
            document.getElementById('activityCancelModal');

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');

        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal =
            document.getElementById('activityCancelModal');

        const reason =
            document.getElementById('activityCancelReason');

        const count =
            document.getElementById('activityCancelCount');

        if (!modal || !reason || !count) {
            return;
        }

        reason.addEventListener('input', function () {
            count.textContent = String(this.value.length);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeActivityCancelModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {
                closeActivityCancelModal();
            }
        });
    });
    </script>


    <!-- =========================================
         CSS กิจกรรมของฉัน
    ========================================== -->

    <style>

    .my-activities-section {
        padding: 45px 0 80px;
        background: #f7f9fc;
    }

    .my-activities-top {
        margin-bottom: 22px;
    }

    .my-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #18345e;
        font-size: 14px;
        font-weight: 600;
    }

    .my-activity-alert {
        margin-bottom: 20px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 9px;
        border-radius: 10px;
        font-size: 14px;
    }

    .my-activity-alert.success {
        color: #17643d;
        background: #e8f7ef;
        border: 1px solid #b8e5cc;
    }

    .my-activity-alert.error {
        color: #a52b32;
        background: #fff0f1;
        border: 1px solid #ffcbd0;
    }

    .my-activities-grid {
        display: grid;
        gap: 22px;
    }

    .my-activity-card {
        display: grid;
        grid-template-columns: 300px 1fr;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #e0e6ee;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(20, 42, 75, .06);
    }

    .my-activity-cover {
        position: relative;
        min-height: 250px;
        overflow: hidden;
        background: #edf1f7;
    }

    .my-activity-cover img {
        width: 100%;
        height: 100%;
        min-height: 250px;
        display: block;
        object-fit: cover;
    }

    .my-activity-state {
        position: absolute;
        top: 15px;
        left: 15px;
        padding: 7px 11px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
    }

    .my-activity-state.registered {
        color: #12603b;
        background: #e4f7ed;
    }

    .my-activity-state.finished {
        color: #596579;
        background: #edf0f4;
    }

    .my-activity-content {
        padding: 27px 30px;
    }

    .my-activity-content h2 {
        margin: 0 0 8px;
        color: #18345e;
        font-size: 21px;
    }

    .my-activity-desc {
        margin: 0 0 18px;
        color: #718096;
        font-size: 13px;
        line-height: 1.7;
    }

    .my-activity-info {
        display: grid;
        gap: 10px;
        margin-bottom: 18px;
    }

    .my-activity-info div {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #52647b;
        font-size: 13px;
    }

    .my-activity-info i {
        width: 18px;
        color: #2468c9;
        text-align: center;
    }

    .my-activity-status-line {
        margin-bottom: 21px;
        padding: 10px 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #12603b;
        background: #f0faf5;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 600;
    }

    .my-activity-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .my-activity-actions form {
        margin: 0;
    }

    .my-detail-btn,
    .my-cancel-btn,
    .my-find-btn {
        min-height: 42px;
        padding: 9px 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 9px;
        font-family: "Prompt", sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }

    .my-detail-btn {
        color: #ffffff;
        background: #18345e;
        border: 1px solid #18345e;
    }

    .my-cancel-btn {
        color: #b4232c;
        background: #ffffff;
        border: 1px solid #edb7bc;
    }

    .my-cancel-btn:hover {
        background: #fff4f5;
    }

    .my-cancel-btn.disabled {
        color: #98a2b3;
        background: #f2f4f7;
        border-color: #e3e6eb;
        cursor: not-allowed;
    }

    .my-empty-card {
        padding: 65px 25px;
        text-align: center;
        background: #ffffff;
        border: 1px solid #e1e7ef;
        border-radius: 16px;
    }

    .my-empty-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 15px;
        display: grid;
        place-items: center;
        color: #2468c9;
        background: #eaf2ff;
        border-radius: 50%;
        font-size: 25px;
    }

    .my-empty-card h2 {
        margin-bottom: 7px;
        color: #18345e;
        font-size: 20px;
    }

    .my-empty-card p {
        margin-bottom: 20px;
        color: #78869a;
        font-size: 13px;
    }

    .my-find-btn {
        color: #ffffff;
        background: #18345e;
        border: 0;
    }

    @media (max-width: 800px) {

        .my-activity-card {
            grid-template-columns: 1fr;
        }

        .my-activity-cover {
            min-height: 220px;
        }

        .my-activity-cover img {
            min-height: 220px;
            max-height: 280px;
        }

    }

    </style>


    <?php

    include 'includes/footer.php';
    exit;
}


// =========================================================
// รายละเอียดกิจกรรม
// =========================================================

if ($activityId > 0) {


    // =========================================
    // ดึงกิจกรรม
    // =========================================

    $stmt = $pdo->prepare("
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

        WHERE a.id = ?

        AND a.publish_status =
            'published'

        LIMIT 1
    ");

    $stmt->execute([
        $activityId
    ]);

    $activity =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    // =========================================
    // ไม่พบ
    // =========================================

    if (!$activity) {

        http_response_code(404);

        $pageTitle =
            'ไม่พบกิจกรรม';

        include 'includes/header.php';

        ?>

        <main class="activities-page">

            <section class="activity-detail-section">

                <div class="activities-container">

                    <a
                        href="activities.php"
                        class="activity-back"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        กลับไปหน้ากิจกรรม

                    </a>

                    <div class="activities-empty">

                        <i class="fa-regular fa-calendar-xmark"></i>

                        <h3>
                            ไม่พบกิจกรรม
                        </h3>

                        <p>
                            กิจกรรมนี้อาจถูกลบ
                            หรือยังไม่ได้เผยแพร่
                        </p>

                    </div>

                </div>

            </section>

        </main>

        <?php

        include 'includes/footer.php';
        exit;
    }


    // =========================================
    // ข้อมูลสถานะ
    // =========================================

    $registeredCount =
        (int)$activity['registered_count'];

    $status =
        getActivityStatus(
            $activity,
            $registeredCount
        );

    $coverImage =
        getActivityCover(
            $activity
        );

    $started =
        activityHasStarted(
            $activity
        );


    // =========================================
    // ตรวจว่าผู้ใช้สมัครแล้วไหม
    // =========================================

    $userRegistration = null;

    if (
        isset($_SESSION['user_id']) &&
        !$isAdmin
    ) {

        $regStmt = $pdo->prepare("
            SELECT id, registration_status, registered_at, cancelled_at

            FROM activity_registrations

            WHERE activity_id = ?

            AND user_id = ?

            LIMIT 1
        ");

        $regStmt->execute([
            $activityId,
            (int)$_SESSION['user_id']
        ]);

        $userRegistration =
            $regStmt->fetch(
                PDO::FETCH_ASSOC
            );
    }

    $userRegistrationActive =
        $userRegistration &&
        ($userRegistration['registration_status'] ?? '') === 'registered';

    $userRegistrationCancelled =
        $userRegistration &&
        ($userRegistration['registration_status'] ?? '') === 'cancelled';

    $detailCancellationInfo =
        activityCancellationInfo(
            $userRegistration['registered_at'] ?? null
        );

    $canCancelRegistration =
        $userRegistrationActive &&
        !$started &&
        $detailCancellationInfo['can_cancel'];


    // =========================================
    // ไฟล์แนบ
    // =========================================

    $fileStmt = $pdo->prepare("
        SELECT *

        FROM activity_files

        WHERE activity_id = ?

        ORDER BY uploaded_at ASC
    ");

    $fileStmt->execute([
        $activityId
    ]);

    $activityFiles =
        $fileStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $pageTitle =
        $activity['title'];

    include 'includes/header.php';

    ?>


    <main class="activities-page">

        <section class="activity-detail-section">

            <div class="activities-container">


                <a
                    href="activities.php"
                    class="activity-back"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    กิจกรรมทั้งหมด

                </a>


                <!-- ข้อความ -->

                <?php if ($activitySuccess): ?>

                    <div class="detail-activity-alert success">

                        <i class="fa-solid fa-circle-check"></i>

                        <?= activityE($activitySuccess) ?>

                    </div>

                <?php endif; ?>


                <?php if ($activityError): ?>

                    <div class="detail-activity-alert error">

                        <i class="fa-solid fa-circle-exclamation"></i>

                        <?= activityE($activityError) ?>

                    </div>

                <?php endif; ?>


                <article class="activity-detail-card">


                    <!-- COVER -->

                    <div class="activity-detail-cover">

                        <img
                            src="<?= activityE($coverImage) ?>"
                            alt="<?= activityE($activity['title']) ?>"
                        >

                        <span
                            class="registration-badge <?= activityE($status['class']) ?>"
                        >

                            <?= activityE($status['text']) ?>

                        </span>

                    </div>


                    <div class="activity-detail-body">


                        <h1 class="activity-detail-title">

                            <?= activityE(
                                $activity['title']
                            ) ?>

                        </h1>


                        <?php if (
                            !empty(
                                $activity['short_description']
                            )
                        ): ?>

                            <p class="activity-detail-short">

                                <?= activityE(
                                    $activity['short_description']
                                ) ?>

                            </p>

                        <?php endif; ?>


                        <!-- =================================
                             ข้อมูลกิจกรรม
                        ================================== -->

                        <div class="activity-info-grid">


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-regular fa-calendar"></i>

                                </div>

                                <div>

                                    <small>
                                        วันที่จัดกิจกรรม
                                    </small>

                                    <strong>

                                        <?= activityE(
                                            thaiActivityDate(
                                                $activity['activity_date']
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-regular fa-clock"></i>

                                </div>

                                <div>

                                    <small>
                                        เวลา
                                    </small>

                                    <strong>

                                        <?= activityE(
                                            activityTime(
                                                $activity['start_time'],
                                                $activity['end_time']
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-solid fa-location-dot"></i>

                                </div>

                                <div>

                                    <small>
                                        สถานที่
                                    </small>

                                    <strong>

                                        <?= activityE(
                                            $activity['location']
                                            ?: '-'
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-solid fa-users"></i>

                                </div>

                                <div>

                                    <small>
                                        จำนวนผู้สมัคร
                                    </small>

                                    <strong>

                                        <?= $registeredCount ?>

                                        <?php if (
                                            !empty(
                                                $activity['max_participants']
                                            )
                                        ): ?>

                                            /
                                            <?= (int)$activity['max_participants'] ?>

                                        <?php endif; ?>

                                        คน

                                    </strong>

                                </div>

                            </div>


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-solid fa-door-open"></i>

                                </div>

                                <div>

                                    <small>
                                        เปิดรับสมัคร
                                    </small>

                                    <strong>

                                        <?= activityE(
                                            thaiActivityDate(
                                                $activity['registration_open']
                                            )
                                        ) ?>

                                        <?= date(
                                            'H:i',
                                            strtotime(
                                                $activity['registration_open']
                                            )
                                        ) ?>

                                        น.

                                    </strong>

                                </div>

                            </div>


                            <div class="activity-info-box">

                                <div class="activity-info-icon">

                                    <i class="fa-solid fa-door-closed"></i>

                                </div>

                                <div>

                                    <small>
                                        ปิดรับสมัคร
                                    </small>

                                    <strong>

                                        <?= activityE(
                                            thaiActivityDate(
                                                $activity['registration_close']
                                            )
                                        ) ?>

                                        <?= date(
                                            'H:i',
                                            strtotime(
                                                $activity['registration_close']
                                            )
                                        ) ?>

                                        น.

                                    </strong>

                                </div>

                            </div>


                        </div>


                        <!-- รายละเอียด -->

                        <div class="activity-detail-block">

                            <h2>
                                รายละเอียดกิจกรรม
                            </h2>

                            <div class="activity-detail-text">

                                <?= nl2br(
                                    activityE(
                                        $activity['description']
                                        ?: '-'
                                    )
                                ) ?>

                            </div>

                        </div>


                        <!-- รับสมัคร -->

                        <div class="activity-detail-block">

                            <h2>
                                การรับสมัคร
                            </h2>

                            <div class="registration-period">

                                <p>

                                    <strong>
                                        จำนวนรับ:
                                    </strong>

                                    <?php if (
                                        !empty(
                                            $activity['max_participants']
                                        )
                                    ): ?>

                                        <?= (int)$activity['max_participants'] ?>
                                        คน

                                    <?php else: ?>

                                        ไม่จำกัด

                                    <?php endif; ?>

                                </p>

                                <p>

                                    <strong>
                                        สมัครแล้ว:
                                    </strong>

                                    <?= $registeredCount ?>
                                    คน

                                </p>

                                <p>

                                    <strong>
                                        สถานะ:
                                    </strong>

                                    <?= activityE(
                                        $status['text']
                                    ) ?>

                                </p>

                            </div>

                        </div>


                        <!-- ไฟล์แนบ -->

                        <?php if (
                            !empty($activityFiles)
                        ): ?>

                            <div class="activity-detail-block">

                                <h2>
                                    ไฟล์แนบกิจกรรม
                                </h2>

                                <div class="activity-files">

                                    <?php foreach (
                                        $activityFiles
                                        as $file
                                    ): ?>

                                        <a
                                            href="download_activity_file.php?id=<?= (int)$file['id'] ?>"
                                            class="activity-file"
                                        >

                                            <i class="fa-solid fa-paperclip"></i>

                                            <span>

                                                <?= activityE(
                                                    $file['original_name']
                                                ) ?>

                                            </span>

                                            <i class="fa-solid fa-download"></i>

                                        </a>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- =================================
                             สมัคร / ดูผู้สมัคร / ยกเลิก
                        ================================== -->

                        <div class="activity-register-area">


                            <div class="register-message">


                                <?php if ($isAdmin): ?>

                                    <i class="fa-solid fa-users"></i>

                                    ดูรายชื่อผู้สมัครเข้าร่วมกิจกรรมนี้


                                <?php elseif ($userRegistrationActive): ?>

                                    <i class="fa-solid fa-circle-check"></i>

                                    คุณลงทะเบียนเข้าร่วมกิจกรรมนี้แล้ว


                                <?php elseif ($userRegistrationCancelled): ?>

                                    <i class="fa-solid fa-circle-xmark"></i>

                                    คุณยกเลิกกิจกรรมนี้แล้ว และไม่สามารถสมัครซ้ำได้


                                <?php elseif (
                                    isset($_SESSION['user_id']) &&
                                    $canRegisterAsMember &&
                                    !$status['can_register']
                                ): ?>

                                    ขณะนี้กิจกรรม

                                    <?= activityE(
                                        $status['text']
                                    ) ?>


                                <?php elseif (
                                    !isset($_SESSION['user_id'])
                                ): ?>

                                    กรุณาเข้าสู่ระบบก่อนสมัครกิจกรรม


                                <?php elseif (!$canRegisterAsMember): ?>

                                    คุณยังไม่ได้รับการอนุมัติเป็นสมาชิกชมรม จึงไม่สามารถลงทะเบียนกิจกรรมได้


                                <?php else: ?>

                                    คุณสามารถสมัครเข้าร่วมกิจกรรมนี้ได้

                                <?php endif; ?>


                            </div>


                            <?php if ($isAdmin): ?>


                                <a
                                    href="admin/registrations/index.php?activity_id=<?= (int)$activity['id'] ?>"
                                    class="activity-register-btn"
                                >

                                    <i class="fa-solid fa-users"></i>

                                    ดูผู้สมัคร

                                </a>


                            <?php elseif ($userRegistrationActive): ?>


                                <div class="detail-registration-actions">


                                    <a
                                        href="activities.php?my=1"
                                        class="activity-register-btn"
                                    >

                                        <i class="fa-regular fa-calendar-check"></i>

                                        กิจกรรมของฉัน

                                    </a>


                                    <?php if ($canCancelRegistration): ?>


                                        <button
                                            type="button"
                                            class="detail-cancel-btn"
                                            data-registration-id="<?= (int)$userRegistration['id'] ?>"
                                            data-activity-title="<?= activityE($activity['title']) ?>"
                                            data-activity-date="<?= activityE(thaiActivityDate($activity['activity_date'])) ?>"
                                            onclick="openActivityCancelModal(this)"
                                        >

                                            <i class="fa-solid fa-xmark"></i>

                                            ยกเลิกการสมัคร

                                        </button>


                                    <?php endif; ?>

                                    <?php if (!$canCancelRegistration && !$started): ?>
                                        <span class="activity-register-btn disabled">
                                            <i class="fa-solid fa-lock"></i>
                                            พ้นกำหนดยกเลิกแล้ว
                                        </span>
                                    <?php endif; ?>


                                </div>


                            <?php elseif ($userRegistrationCancelled): ?>


                                <span class="activity-register-btn disabled">
                                    <i class="fa-solid fa-ban"></i>
                                    ไม่สามารถสมัครซ้ำได้
                                </span>


                            <?php elseif (
                                isset($_SESSION['user_id']) &&
                                $canRegisterAsMember &&
                                !$status['can_register']
                            ): ?>


                                <span
                                    class="activity-register-btn disabled"
                                >

                                    <?= activityE(
                                        $status['text']
                                    ) ?>

                                </span>


                            <?php elseif (
                                !isset($_SESSION['user_id'])
                            ): ?>


                                <a
                                    href="login.php"
                                    class="activity-register-btn"
                                >

                                    <i class="fa-regular fa-user"></i>

                                    เข้าสู่ระบบเพื่อสมัคร

                                </a>


                            <?php elseif (!$canRegisterAsMember): ?>


                                <span class="activity-register-btn disabled">
                                    คุณยังไม่ได้รับการอนุมัติเป็นสมาชิกชมรม จึงไม่สามารถลงทะเบียนกิจกรรมได้
                                </span>


                            <?php else: ?>


                                <button
                                    type="button"
                                    onclick="openActivityRegisterModal()"
                                    class="activity-register-btn"
                                >

                                    <i class="fa-regular fa-calendar-check"></i>

                                    สมัครกิจกรรม

                                </button>


                            <?php endif; ?>


                        </div>


                    </div>

                </article>

            </div>

        </section>

    </main>

    <!-- =========================================
         MODAL ยืนยันสมัครกิจกรรม
    ========================================== -->

    <?php if (!$isAdmin && isset($_SESSION['user_id']) && $canRegisterAsMember && !$userRegistration): ?>
        <div
            class="activity-cancel-overlay"
            id="activityRegisterModal"
            aria-hidden="true"
        >
            <div
                class="activity-cancel-modal activity-register-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="activityRegisterTitle"
            >
                <div class="activity-cancel-icon register-icon">
                    <i class="fa-regular fa-calendar-check"></i>
                </div>

                <h2 id="activityRegisterTitle">ยืนยันสมัครกิจกรรม</h2>

                <p class="activity-cancel-description">
                    ยืนยันสมัคร “<?= activityE($activity['title']) ?>” หรือไม่
                </p>

                <div class="activity-register-policy">
                    <div>
                        <i class="fa-regular fa-clock"></i>
                        ยกเลิกได้เฉพาะภายใน 48 ชั่วโมงนับจากเวลาที่สมัคร
                    </div>
                    <div>
                        <i class="fa-solid fa-ban"></i>
                        เมื่อยกเลิกแล้ว จะไม่สามารถสมัครกิจกรรมนี้ซ้ำได้
                    </div>
                </div>

                <form action="activity_register.php" method="POST">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= activityE($activityCsrf) ?>"
                    >
                    <input
                        type="hidden"
                        name="activity_id"
                        value="<?= (int)$activity['id'] ?>"
                    >

                    <div class="activity-cancel-actions">
                        <button
                            type="button"
                            class="activity-cancel-back"
                            onclick="closeActivityRegisterModal()"
                        >
                            ย้อนกลับ
                        </button>
                        <button type="submit" class="activity-register-confirm">
                            <i class="fa-solid fa-check"></i>
                            ยืนยันสมัครกิจกรรม
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>


    <!-- =========================================
         MODAL ยกเลิกการสมัครกิจกรรม
    ========================================== -->

    <div
        class="activity-cancel-overlay"
        id="activityCancelModal"
        aria-hidden="true"
    >
        <div
            class="activity-cancel-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="activityCancelTitle"
        >

            <div class="activity-cancel-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h2 id="activityCancelTitle">
                ยืนยันการยกเลิกกิจกรรม
            </h2>

            <p class="activity-cancel-description">
                คุณต้องการยกเลิกการลงทะเบียนกิจกรรม
                <strong id="activityCancelName"></strong>
                ใช่หรือไม่
            </p>

            <div class="activity-cancel-summary">
                <span>วันที่จัดกิจกรรม</span>
                <strong id="activityCancelDate">-</strong>
            </div>

            <p class="activity-cancel-warning">
                ยกเลิกได้ภายใน 48 ชั่วโมงหลังสมัครเท่านั้น และเมื่อยกเลิกแล้วจะไม่สามารถสมัครกิจกรรมนี้ซ้ำได้
            </p>

            <form
                action="activity_cancel.php"
                method="POST"
                id="activityCancelForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= activityE($activityCsrf) ?>"
                >

                <input
                    type="hidden"
                    name="registration_id"
                    id="activityCancelRegistrationId"
                    value=""
                >

                <div class="activity-cancel-field">

                    <label for="activityCancelReason">
                        สาเหตุการยกเลิก
                    </label>

                    <textarea
                        name="cancel_reason"
                        id="activityCancelReason"
                        rows="4"
                        maxlength="500"
                        placeholder="ระบุสาเหตุ"
                        required
                    ></textarea>

                    <div class="activity-cancel-counter">
                        <span id="activityCancelCount">0</span>/500
                    </div>

                </div>

                <div class="activity-cancel-actions">

                    <button
                        type="button"
                        class="activity-cancel-back"
                        onclick="closeActivityCancelModal()"
                    >
                        ย้อนกลับ
                    </button>

                    <button
                        type="submit"
                        class="activity-cancel-confirm"
                    >
                        ยืนยันการยกเลิก
                    </button>

                </div>

            </form>

        </div>
    </div>

    <style>
    .activity-cancel-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(17, 28, 45, .58);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .22s ease, visibility .22s ease;
    }

    .activity-cancel-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .activity-cancel-modal {
        width: min(100%, 500px);
        padding: 29px 28px 23px;
        background: #ffffff;
        border: 1px solid #e4e8ef;
        border-radius: 15px;
        box-shadow: 0 24px 70px rgba(12, 31, 58, .25);
        transform: translateY(14px) scale(.985);
        transition: transform .22s ease;
    }

    .activity-cancel-overlay.show .activity-cancel-modal {
        transform: translateY(0) scale(1);
    }

    .activity-cancel-icon {
        width: 58px;
        height: 58px;
        margin: 0 auto 14px;
        display: grid;
        place-items: center;
        color: #db2d32;
        background: #fde9ea;
        border-radius: 50%;
        font-size: 25px;
    }

    .activity-cancel-modal h2 {
        margin: 0;
        color: #18345e;
        text-align: center;
        font-size: 21px;
        font-weight: 700;
    }

    .activity-cancel-description {
        margin: 8px 0 24px;
        color: #718096;
        text-align: center;
        font-size: 12px;
        line-height: 1.7;
    }

    .activity-cancel-field {
        position: relative;
    }

    .activity-cancel-field label {
        display: block;
        margin-bottom: 8px;
        color: #18345e;
        font-size: 13px;
        font-weight: 600;
    }

    .activity-cancel-field textarea {
        width: 100%;
        min-height: 105px;
        padding: 13px 14px 27px;
        resize: vertical;
        box-sizing: border-box;
        color: #334155;
        background: #fbfcfe;
        border: 1px solid #dbe2eb;
        border-radius: 9px;
        outline: none;
        font-family: "Prompt", sans-serif;
        font-size: 13px;
        line-height: 1.65;
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .activity-cancel-field textarea:focus {
        background: #ffffff;
        border-color: #2e6cca;
        box-shadow: 0 0 0 3px rgba(46, 108, 202, .09);
    }

    .activity-cancel-counter {
        position: absolute;
        right: 11px;
        bottom: 8px;
        color: #98a2b3;
        font-size: 10px;
    }

    .activity-cancel-actions {
        margin-top: 23px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .activity-cancel-back,
    .activity-cancel-confirm {
        min-height: 40px;
        padding: 9px 17px;
        border-radius: 8px;
        font-family: "Prompt", sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: .18s ease;
    }

    .activity-cancel-back {
        color: #18345e;
        background: #ffffff;
        border: 1px solid #d7dee8;
    }

    .activity-cancel-back:hover {
        background: #f6f8fb;
    }

    .activity-cancel-confirm {
        color: #ffffff;
        background: #e1252b;
        border: 1px solid #e1252b;
    }

    .activity-cancel-confirm:hover {
        background: #c91f25;
        border-color: #c91f25;
    }

    @media (max-width: 520px) {
        .activity-cancel-modal {
            padding: 24px 18px 19px;
        }

        .activity-cancel-actions {
            flex-direction: column-reverse;
        }

        .activity-cancel-back,
        .activity-cancel-confirm,
        .activity-register-confirm {
            width: 100%;
        }
    }
    </style>

    <style>
    .activity-cancel-description strong {
        display: block;
        margin: 6px 0;
        color: #17365f;
        font-size: 15px;
    }

    .activity-cancel-summary {
        margin-bottom: 12px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        background: #f6f8fb;
        border: 1px solid #e1e7ef;
        border-radius: 9px;
    }

    .activity-cancel-summary span {
        color: #77869a;
        font-size: 11px;
    }

    .activity-cancel-summary strong {
        color: #294866;
        text-align: right;
        font-size: 12px;
    }

    .activity-cancel-warning {
        margin: 0 0 18px;
        color: #a15a17;
        font-size: 10px;
        line-height: 1.65;
    }
    </style>

    <script>
    function openActivityRegisterModal() {
        const modal = document.getElementById('activityRegisterModal');
        if (!modal) return;

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeActivityRegisterModal() {
        const modal = document.getElementById('activityRegisterModal');
        if (!modal) return;

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function openActivityCancelModal(trigger) {
        const modal =
            document.getElementById('activityCancelModal');

        const idInput =
            document.getElementById('activityCancelRegistrationId');

        const reason =
            document.getElementById('activityCancelReason');

        const count =
            document.getElementById('activityCancelCount');

        const activityName =
            document.getElementById('activityCancelName');

        const activityDate =
            document.getElementById('activityCancelDate');

        idInput.value = trigger.dataset.registrationId;
        activityName.textContent = '“' + trigger.dataset.activityTitle + '”';
        activityDate.textContent = trigger.dataset.activityDate || '-';
        reason.value = '';
        count.textContent = '0';

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            reason.focus();
        }, 120);
    }

    function closeActivityCancelModal() {
        const modal =
            document.getElementById('activityCancelModal');

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');

        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const registerModal =
            document.getElementById('activityRegisterModal');

        if (registerModal) {
            registerModal.addEventListener('click', function (event) {
                if (event.target === registerModal) {
                    closeActivityRegisterModal();
                }
            });
        }

        const modal =
            document.getElementById('activityCancelModal');

        const reason =
            document.getElementById('activityCancelReason');

        const count =
            document.getElementById('activityCancelCount');

        if (!modal || !reason || !count) {
            return;
        }

        reason.addEventListener('input', function () {
            count.textContent = String(this.value.length);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeActivityCancelModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                registerModal &&
                registerModal.classList.contains('show')
            ) {
                closeActivityRegisterModal();
            }

            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {
                closeActivityCancelModal();
            }
        });
    });
    </script>


    <style>

    .detail-activity-alert {
        margin: 18px 0;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 8px;
        border-radius: 10px;
        font-size: 13px;
    }

    .detail-activity-alert.success {
        color: #17643d;
        background: #e8f7ef;
        border: 1px solid #b8e5cc;
    }

    .detail-activity-alert.error {
        color: #a52b32;
        background: #fff0f1;
        border: 1px solid #ffcbd0;
    }

    .detail-registration-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .detail-registration-actions form {
        margin: 0;
    }

    .detail-cancel-btn {
        min-height: 48px;
        padding: 11px 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: #b4232c;
        background: #ffffff;
        border: 1px solid #e9adb3;
        border-radius: 10px;
        font-family: "Prompt", sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .detail-cancel-btn:hover {
        background: #fff3f4;
    }

    </style>


    <?php

    include 'includes/footer.php';
    exit;
}


// =========================================================
// กิจกรรมทั้งหมด
// =========================================================

$stmt = $pdo->query("
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
");

$activities =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


include 'includes/header.php';

?>

<main class="activities-page">


    <!-- =================================
         HERO
    ================================== -->

    <section class="activities-hero activities-list-hero">

        <div class="activities-container">

            <div class="activities-label">
                ACTIVITIES
            </div>

            <h1 class="activities-heading">
                กิจกรรม
            </h1>

            <p class="activities-intro">

                ติดตามกิจกรรมของชมรมบิตคอม
                ดูรายละเอียด วันเวลา สถานที่
                และสมัครเข้าร่วมกิจกรรมที่เปิดรับสมัคร

            </p>


            <?php if (
                isset($_SESSION['user_id']) &&
                (($_SESSION['role'] ?? '') !== 'admin')
            ): ?>

                <div class="activities-hero-actions">

                    <a
                        href="activities.php?my=1"
                        class="activities-my-link"
                    >

                        <i class="fa-regular fa-calendar-check"></i>

                        กิจกรรมของฉัน

                    </a>

                </div>

            <?php endif; ?>


        </div>

    </section>


    <!-- =================================
         LIST
    ================================== -->

    <section class="activities-list-section">

        <div class="activities-container">

            <div class="activities-grid">


                <?php if (
                    !empty($activities)
                ): ?>


                    <?php foreach (
                        $activities
                        as $index => $activity
                    ): ?>


                        <?php

                        $registeredCount =
                            (int)$activity[
                                'registered_count'
                            ];

                        $status =
                            getActivityStatus(
                                $activity,
                                $registeredCount
                            );

                        $coverImage =
                            getActivityCover(
                                $activity
                            );

                        $isNew =
                            isActivityNew($activity);

                        ?>


                        <a
                            href="activities.php?id=<?= (int)$activity['id'] ?>"
                            class="activity-list-card <?= $isNew ? 'latest-activity-card' : '' ?>"
                        >


                            <div class="activity-list-image">

                                <img
                                    src="<?= activityE($coverImage) ?>"
                                    alt="<?= activityE($activity['title']) ?>"
                                >

                                <span
                                    class="registration-badge <?= activityE($status['class']) ?>"
                                >

                                    <?= activityE(
                                        $status['text']
                                    ) ?>

                                </span>

                                <?php if ($isNew): ?>

                                    <span class="activity-new-badge">
                                        NEW
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="activity-list-content">


                                <div class="activity-list-meta">


                                    <span>

                                        <i class="fa-regular fa-calendar"></i>

                                        <?= activityE(
                                            thaiActivityDate(
                                                $activity['activity_date']
                                            )
                                        ) ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $activity['location']
                                        )
                                    ): ?>

                                        <span>

                                            <i class="fa-solid fa-location-dot"></i>

                                            <?= activityE(
                                                $activity['location']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <span>

                                        <i class="fa-solid fa-users"></i>

                                        <?= $registeredCount ?>

                                        <?php if (
                                            !empty(
                                                $activity['max_participants']
                                            )
                                        ): ?>

                                            /
                                            <?= (int)$activity['max_participants'] ?>

                                        <?php endif; ?>

                                        คน

                                    </span>


                                </div>


                                <h2 class="activity-list-title">

                                    <?= activityE(
                                        $activity['title']
                                    ) ?>

                                </h2>


                                <p class="activity-list-description">

                                    <?= activityE(
                                        $activity['short_description']
                                        ?: 'ดูรายละเอียดกิจกรรมเพิ่มเติม'
                                    ) ?>

                                </p>


                                <div class="activity-list-footer">

                                    <span class="activity-list-more">
                                        ดูรายละเอียด
                                    </span>

                                    <span class="activity-list-arrow">

                                        <i class="fa-solid fa-arrow-right"></i>

                                    </span>

                                </div>


                            </div>


                        </a>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="activities-empty">

                        <i class="fa-regular fa-calendar"></i>

                        <h3>
                            ยังไม่มีกิจกรรม
                        </h3>

                        <p>
                            เมื่อผู้ดูแลระบบเผยแพร่กิจกรรม
                            รายการกิจกรรมจะปรากฏที่นี่โดยอัตโนมัติ
                        </p>

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </section>


</main>


<?php
include 'includes/footer.php';
?>
