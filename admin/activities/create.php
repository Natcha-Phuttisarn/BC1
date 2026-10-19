<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// create.php
// หน้านี้ใช้สำหรับเพิ่มกิจกรรมใหม่
// แสดงวันที่แบบภาษาไทย พ.ศ. และเวลาแบบ 24 ชั่วโมง

require_once __DIR__ . '/../includes/admin_auth.php';

$activityCreateCsrf = $_SESSION['admin_activity_create_csrf'] ?? '';

if (
    !is_string($activityCreateCsrf) ||
    preg_match('/\A[a-f0-9]{64}\z/D', $activityCreateCsrf) !== 1
) {
    $activityCreateCsrf = bin2hex(random_bytes(32));
    $_SESSION['admin_activity_create_csrf'] = $activityCreateCsrf;
}

$adminPageTitle = 'เพิ่มกิจกรรม';

$error = $_SESSION['activity_error'] ?? '';
$old = $_SESSION['activity_old'] ?? [];

unset(
    $_SESSION['activity_error'],
    $_SESSION['activity_old']
);


// =========================================
// แสดงข้อมูลเดิม
// =========================================

function oldActivity(
    array $old,
    string $key,
    string $default = ''
): string {

    return htmlspecialchars(
        (string)($old[$key] ?? $default),
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================
// แยกวันที่
// =========================================

function getDateParts(
    string $value
): array {

    if ($value === '') {

        return [
            'day' => '',
            'month' => '',
            'year' => ''
        ];
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {

        return [
            'day' => '',
            'month' => '',
            'year' => ''
        ];
    }

    return [
        'day' => date('d', $timestamp),
        'month' => date('m', $timestamp),
        'year' => (int)date('Y', $timestamp) + 543
    ];
}


// =========================================
// แยกเวลา
// =========================================

function getTimeParts(
    string $value
): array {

    if ($value === '') {

        return [
            'hour' => '',
            'minute' => ''
        ];
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {

        return [
            'hour' => '',
            'minute' => ''
        ];
    }

    return [
        'hour' => date('H', $timestamp),
        'minute' => date('i', $timestamp)
    ];
}


// =========================================
// ข้อมูลเดิม
// =========================================

$activityDateParts =
    getDateParts(
        $old['activity_date'] ?? ''
    );

$startTimeParts =
    getTimeParts(
        $old['start_time'] ?? ''
    );

$endTimeParts =
    getTimeParts(
        $old['end_time'] ?? ''
    );

$registrationOpenDate =
    getDateParts(
        $old['registration_open'] ?? ''
    );

$registrationOpenTime =
    getTimeParts(
        $old['registration_open'] ?? ''
    );

$registrationCloseDate =
    getDateParts(
        $old['registration_close'] ?? ''
    );

$registrationCloseTime =
    getTimeParts(
        $old['registration_close'] ?? ''
    );


// =========================================
// เดือนภาษาไทย
// =========================================

$thaiMonths = [
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

$currentThaiYear =
    (int)date('Y') + 543;


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>


<section class="panel full-panel">


    <!-- =================================
         หัวข้อ
    ================================== -->

    <div class="panel-head">

        <div>

            <small>
                ACTIVITIES
            </small>

            <h3>
                เพิ่มกิจกรรมใหม่
            </h3>

        </div>


        <a
            href="index.php"
            class="secondary-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            กลับ

        </a>

    </div>


    <!-- =================================
         ERROR
    ================================== -->

    <?php if ($error): ?>

        <div class="form-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================
         FORM
    ================================== -->

    <form
        action="save.php"
        method="POST"
        enctype="multipart/form-data"
        class="activity-form"
        id="activityForm"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($activityCreateCsrf, ENT_QUOTES, 'UTF-8') ?>"
        >


        <!-- =================================
             ค่าจริงส่งไป save.php
        ================================== -->

        <input
            type="hidden"
            name="activity_date"
            id="activity_date"
        >

        <input
            type="hidden"
            name="start_time"
            id="start_time"
        >

        <input
            type="hidden"
            name="end_time"
            id="end_time"
        >

        <input
            type="hidden"
            name="registration_open"
            id="registration_open"
        >

        <input
            type="hidden"
            name="registration_close"
            id="registration_close"
        >


        <!-- =================================
             ข้อมูลกิจกรรม
        ================================== -->

        <div class="form-section">

            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-regular fa-pen-to-square"></i>

                </div>

                <div>

                    <h4>
                        ข้อมูลกิจกรรม
                    </h4>

                    <p>
                        กรอกชื่อและรายละเอียดของกิจกรรม
                    </p>

                </div>

            </div>


            <div class="form-group full">

                <label>
                    ชื่อกิจกรรม <span>*</span>
                </label>

                <input
                    type="text"
                    name="title"
                    value="<?= oldActivity($old, 'title') ?>"
                    placeholder="เช่น Workshop พัฒนาเว็บไซต์"
                    required
                >

            </div>


            <div class="form-group full">

                <label>
                    คำอธิบายสั้น
                </label>

                <input
                    type="text"
                    name="short_description"
                    value="<?= oldActivity($old, 'short_description') ?>"
                    maxlength="255"
                    placeholder="ข้อความสั้น ๆ สำหรับแสดงบนการ์ดกิจกรรม"
                >

            </div>


            <div class="form-group full">

                <label>
                    รายละเอียดกิจกรรม <span>*</span>
                </label>

                <textarea
                    name="description"
                    rows="7"
                    placeholder="รายละเอียด วัตถุประสงค์ หรือข้อมูลเพิ่มเติมของกิจกรรม"
                    required
                ><?= oldActivity($old, 'description') ?></textarea>

            </div>

        </div>


        <!-- =================================
             วัน เวลา สถานที่
        ================================== -->

        <div class="form-section">

            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-regular fa-calendar"></i>

                </div>

                <div>

                    <h4>
                        วัน เวลา และสถานที่
                    </h4>

                    <p>
                        กำหนดวัน เวลา และสถานที่จัดกิจกรรม
                    </p>

                </div>

            </div>


            <!-- วันที่ -->

            <div class="form-group full">

                <label>
                    วันที่จัดกิจกรรม <span>*</span>
                </label>


                <div class="thai-date-row">


                    <select
                        id="activity_day"
                        required
                    >

                        <option value="">
                            วัน
                        </option>

                        <?php for ($day = 1; $day <= 31; $day++): ?>

                            <?php $dayValue = sprintf('%02d', $day); ?>

                            <option
                                value="<?= $dayValue ?>"
                                <?= $activityDateParts['day'] === $dayValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $day ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select
                        id="activity_month"
                        required
                    >

                        <option value="">
                            เดือน
                        </option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $monthValue = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $monthValue ?>"
                                <?= $activityDateParts['month'] === $monthValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select
                        id="activity_year"
                        required
                    >

                        <option value="">
                            พ.ศ.
                        </option>

                        <?php for (
                            $year = $currentThaiYear - 1;
                            $year <= $currentThaiYear + 5;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$activityDateParts['year'] === $year
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

            </div>


            <div class="form-grid">


                <!-- สถานที่ -->

                <div class="form-group">

                    <label>
                        สถานที่ <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="<?= oldActivity($old, 'location') ?>"
                        placeholder="เช่น ห้องปฏิบัติการคอมพิวเตอร์"
                        required
                    >

                </div>


                <!-- เวลาเริ่ม -->

                <div class="form-group">

                    <label>
                        เวลาเริ่ม <span>*</span>
                    </label>


                    <div class="thai-time-row">


                        <select
                            id="start_hour"
                            required
                        >

                            <option value="">
                                ชม.
                            </option>

                            <?php for ($hour = 0; $hour <= 23; $hour++): ?>

                                <?php $h = sprintf('%02d', $hour); ?>

                                <option
                                    value="<?= $h ?>"
                                    <?= $startTimeParts['hour'] === $h
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= $h ?>
                                </option>

                            <?php endfor; ?>

                        </select>


                        <span class="time-colon">
                            :
                        </span>


                        <select
                            id="start_minute"
                            required
                        >

                            <option value="">
                                นาที
                            </option>

                            <?php for ($minute = 0; $minute <= 59; $minute++): ?>

                                <?php $m = sprintf('%02d', $minute); ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $startTimeParts['minute'] === $m
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= $m ?>
                                </option>

                            <?php endfor; ?>

                        </select>


                        <span class="thai-time-label">
                            น.
                        </span>

                    </div>

                </div>


                <!-- เวลาสิ้นสุด -->

                <div class="form-group">

                    <label>
                        เวลาสิ้นสุด <span>*</span>
                    </label>


                    <div class="thai-time-row">


                        <select
                            id="end_hour"
                            required
                        >

                            <option value="">
                                ชม.
                            </option>

                            <?php for ($hour = 0; $hour <= 23; $hour++): ?>

                                <?php $h = sprintf('%02d', $hour); ?>

                                <option
                                    value="<?= $h ?>"
                                    <?= $endTimeParts['hour'] === $h
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= $h ?>
                                </option>

                            <?php endfor; ?>

                        </select>


                        <span class="time-colon">
                            :
                        </span>


                        <select
                            id="end_minute"
                            required
                        >

                            <option value="">
                                นาที
                            </option>

                            <?php for ($minute = 0; $minute <= 59; $minute++): ?>

                                <?php $m = sprintf('%02d', $minute); ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $endTimeParts['minute'] === $m
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= $m ?>
                                </option>

                            <?php endfor; ?>

                        </select>


                        <span class="thai-time-label">
                            น.
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================
             การรับสมัคร
        ================================== -->

        <div class="form-section">


            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-solid fa-user-plus"></i>

                </div>

                <div>

                    <h4>
                        การรับสมัคร
                    </h4>

                    <p>
                        กำหนดช่วงเวลารับสมัครและจำนวนผู้เข้าร่วม
                    </p>

                </div>

            </div>


            <!-- =================================
                 เปิดรับสมัคร
            ================================== -->

            <div class="thai-registration-box">


                <label class="thai-registration-title">

                    <i class="fa-solid fa-door-open"></i>

                    เปิดรับสมัคร <span>*</span>

                </label>


                <!-- วันที่ -->

                <div class="thai-registration-date">


                    <select
                        id="open_day"
                        required
                    >

                        <option value="">
                            วัน
                        </option>

                        <?php for ($day = 1; $day <= 31; $day++): ?>

                            <?php $dayValue = sprintf('%02d', $day); ?>

                            <option
                                value="<?= $dayValue ?>"
                                <?= $registrationOpenDate['day'] === $dayValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $day ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select
                        id="open_month"
                        required
                    >

                        <option value="">
                            เดือน
                        </option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $monthValue = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $monthValue ?>"
                                <?= $registrationOpenDate['month'] === $monthValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select
                        id="open_year"
                        required
                    >

                        <option value="">
                            พ.ศ.
                        </option>

                        <?php for (
                            $year = $currentThaiYear - 1;
                            $year <= $currentThaiYear + 5;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$registrationOpenDate['year'] === $year
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <!-- เวลา -->

                <div class="thai-registration-time">

                    <span class="registration-time-word">
                        เวลา
                    </span>


                    <select
                        id="open_hour"
                        required
                    >

                        <option value="">
                            ชม.
                        </option>

                        <?php for ($hour = 0; $hour <= 23; $hour++): ?>

                            <?php $h = sprintf('%02d', $hour); ?>

                            <option
                                value="<?= $h ?>"
                                <?= $registrationOpenTime['hour'] === $h
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $h ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <span class="time-colon">
                        :
                    </span>


                    <select
                        id="open_minute"
                        required
                    >

                        <option value="">
                            นาที
                        </option>

                        <?php for ($minute = 0; $minute <= 59; $minute++): ?>

                            <?php $m = sprintf('%02d', $minute); ?>

                            <option
                                value="<?= $m ?>"
                                <?= $registrationOpenTime['minute'] === $m
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $m ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <span class="thai-time-label">
                        น.
                    </span>

                </div>


            </div>


            <!-- =================================
                 ปิดรับสมัคร
            ================================== -->

            <div class="thai-registration-box">


                <label class="thai-registration-title">

                    <i class="fa-solid fa-door-closed"></i>

                    ปิดรับสมัคร <span>*</span>

                </label>


                <!-- วันที่ -->

                <div class="thai-registration-date">


                    <select
                        id="close_day"
                        required
                    >

                        <option value="">
                            วัน
                        </option>

                        <?php for ($day = 1; $day <= 31; $day++): ?>

                            <?php $dayValue = sprintf('%02d', $day); ?>

                            <option
                                value="<?= $dayValue ?>"
                                <?= $registrationCloseDate['day'] === $dayValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $day ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select
                        id="close_month"
                        required
                    >

                        <option value="">
                            เดือน
                        </option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $monthValue = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $monthValue ?>"
                                <?= $registrationCloseDate['month'] === $monthValue
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select
                        id="close_year"
                        required
                    >

                        <option value="">
                            พ.ศ.
                        </option>

                        <?php for (
                            $year = $currentThaiYear - 1;
                            $year <= $currentThaiYear + 5;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$registrationCloseDate['year'] === $year
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <!-- เวลา -->

                <div class="thai-registration-time">

                    <span class="registration-time-word">
                        เวลา
                    </span>


                    <select
                        id="close_hour"
                        required
                    >

                        <option value="">
                            ชม.
                        </option>

                        <?php for ($hour = 0; $hour <= 23; $hour++): ?>

                            <?php $h = sprintf('%02d', $hour); ?>

                            <option
                                value="<?= $h ?>"
                                <?= $registrationCloseTime['hour'] === $h
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $h ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <span class="time-colon">
                        :
                    </span>


                    <select
                        id="close_minute"
                        required
                    >

                        <option value="">
                            นาที
                        </option>

                        <?php for ($minute = 0; $minute <= 59; $minute++): ?>

                            <?php $m = sprintf('%02d', $minute); ?>

                            <option
                                value="<?= $m ?>"
                                <?= $registrationCloseTime['minute'] === $m
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $m ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <span class="thai-time-label">
                        น.
                    </span>

                </div>


                <small class="form-help">

                    เมื่อถึงวันและเวลานี้
                    ระบบจะปิดรับสมัครอัตโนมัติ

                </small>


            </div>


            <!-- =================================
                 จำนวน / สถานะ
            ================================== -->

            <div class="form-grid">


                <div class="form-group">

                    <label>
                        จำนวนผู้เข้าร่วมสูงสุด
                    </label>

                    <input
                        type="number"
                        name="max_participants"
                        value="<?= oldActivity($old, 'max_participants') ?>"
                        min="1"
                        placeholder="เช่น 50"
                    >

                    <small class="form-help">

                        เมื่อผู้สมัครครบจำนวน
                        ระบบจะปิดรับสมัครอัตโนมัติ

                    </small>

                </div>


                <div class="form-group">

                    <label>
                        สถานะกิจกรรม
                    </label>

                    <select name="publish_status">

                        <option
                            value="draft"
                            <?= ($old['publish_status'] ?? 'draft') === 'draft'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            ฉบับร่าง
                        </option>

                        <option
                            value="published"
                            <?= ($old['publish_status'] ?? '') === 'published'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            เผยแพร่
                        </option>

                        <option
                            value="hidden"
                            <?= ($old['publish_status'] ?? '') === 'hidden'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            ซ่อนกิจกรรม
                        </option>

                    </select>

                </div>


            </div>


        </div>


        <!-- =================================
             รูปและไฟล์แนบ
        ================================== -->

        <div class="form-section">


            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-regular fa-image"></i>

                </div>

                <div>

                    <h4>
                        รูปภาพและไฟล์แนบ
                    </h4>

                    <p>
                        เพิ่มรูปหน้าปกและเอกสารที่เกี่ยวข้อง
                    </p>

                </div>

            </div>


            <div class="form-grid">


                <!-- รูป -->

                <div class="form-group">

                    <label>
                        รูปหน้าปกกิจกรรม
                    </label>


                    <div class="activity-upload-card">

                        <div class="activity-upload-icon">

                            <i class="fa-regular fa-image"></i>

                        </div>

                        <strong>
                            เลือกรูปหน้าปก
                        </strong>

                        <span>
                            JPG, JPEG, PNG, WEBP ไม่เกิน 5 MB
                        </span>

                        <input
                            type="file"
                            name="cover_image"
                            id="coverImage"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="activity-file-input"
                        >

                        <div
                            class="activity-file-name"
                            id="coverFileName"
                        >
                            ยังไม่ได้เลือกรูป
                        </div>

                    </div>


                    <div
                        class="activity-image-preview"
                        id="imagePreviewBox"
                    >

                        <img
                            src=""
                            id="imagePreview"
                            alt="ตัวอย่างรูปหน้าปก"
                        >

                    </div>


                </div>


                <!-- ไฟล์แนบ -->

                <div class="form-group">

                    <label>
                        ไฟล์แนบกิจกรรม
                    </label>


                    <div class="activity-upload-card">

                        <div class="activity-upload-icon">

                            <i class="fa-solid fa-paperclip"></i>

                        </div>

                        <strong>
                            เลือกไฟล์แนบ
                        </strong>

                        <span>
                            สามารถเลือกได้หลายไฟล์
                        </span>

                        <input
                            type="file"
                            name="attachments[]"
                            id="attachments"
                            class="activity-file-input"
                            multiple
                        >

                        <div
                            class="activity-file-name"
                            id="attachmentNames"
                        >
                            ยังไม่ได้เลือกไฟล์
                        </div>

                    </div>


                </div>


            </div>


        </div>


        <!-- =================================
             ปุ่ม
        ================================== -->

        <div class="form-actions">

            <a
                href="index.php"
                class="secondary-btn"
            >
                ยกเลิก
            </a>


            <button
                type="submit"
                name="action"
                value="draft"
                class="draft-btn"
            >

                <i class="fa-regular fa-floppy-disk"></i>

                บันทึกฉบับร่าง

            </button>


            <button
                type="submit"
                name="action"
                value="publish"
                class="primary-btn"
            >

                <i class="fa-solid fa-paper-plane"></i>

                เผยแพร่กิจกรรม

            </button>

        </div>


    </form>


</section>


<style>

/* ========================================
   วันที่ภาษาไทย
======================================== */

.thai-date-row,
.thai-registration-date {
    display: grid;
    grid-template-columns:
        90px
        minmax(180px, 1fr)
        130px;
    gap: 10px;
}

.thai-date-row select,
.thai-registration-date select,
.thai-time-row select,
.thai-registration-time select {
    min-height: 48px;
    padding: 10px 12px;
    border: 1px solid #d9e1ec;
    border-radius: 10px;
    background: #ffffff;
    color: #263954;
    font-family: "Prompt", sans-serif;
    outline: none;
}

.thai-date-row select:focus,
.thai-registration-date select:focus,
.thai-time-row select:focus,
.thai-registration-time select:focus {
    border-color: #2468c9;
    box-shadow:
        0 0 0 3px
        rgba(36, 104, 201, .08);
}


/* ========================================
   เวลาเริ่ม / สิ้นสุด
======================================== */

.thai-time-row {
    display: flex;
    align-items: center;
    gap: 7px;
}

.thai-time-row select {
    width: 95px;
    flex: 0 0 95px;
}


/* ========================================
   กล่องเปิด / ปิดรับสมัคร
======================================== */

.thai-registration-box {
    margin-bottom: 22px;
    padding: 20px;
    border: 1px solid #e1e7ef;
    border-radius: 14px;
    background: #fbfcfe;
}

.thai-registration-title {
    display: block;
    margin-bottom: 14px;
    color: #18345e;
    font-size: 14px;
    font-weight: 600;
}

.thai-registration-title i {
    margin-right: 6px;
    color: #2468c9;
}

.thai-registration-title span {
    color: #e5484d;
}


/* ========================================
   เวลาเปิด / ปิดรับสมัคร
   จุดนี้จัดใหม่ให้นาทีอยู่ใกล้ชั่วโมง
======================================== */

.thai-registration-time {
    margin-top: 14px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 7px;
}

.registration-time-word {
    margin-right: 5px;
    color: #52647b;
    font-size: 13px;
    font-weight: 500;
}

.thai-registration-time select {
    width: 95px;
    flex: 0 0 95px;
}

.time-colon {
    margin: 0 1px;
    color: #18345e;
    font-size: 20px;
    font-weight: 700;
}

.thai-time-label {
    margin-left: 2px;
    color: #66758a;
    font-size: 13px;
}


/* ========================================
   Upload
======================================== */

.activity-upload-card {
    padding: 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 9px;
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    background: #f8fafc;
    text-align: center;
}

.activity-upload-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #eaf2ff;
    color: #1762c6;
    font-size: 20px;
}

.activity-upload-card strong {
    color: #18345e;
    font-size: 14px;
}

.activity-upload-card span {
    color: #7b8799;
    font-size: 12px;
}

.activity-file-input {
    width: 100%;
    margin-top: 8px;
    padding: 10px;
    border: 1px solid #d5dce8;
    border-radius: 9px;
    background: #ffffff;
}

.activity-file-input::file-selector-button {
    margin-right: 10px;
    padding: 8px 13px;
    border: 0;
    border-radius: 7px;
    background: #1762c6;
    color: #ffffff;
    font-family: "Prompt", sans-serif;
    cursor: pointer;
}

.activity-file-name {
    width: 100%;
    padding: 8px 10px;
    color: #5f6b7a;
    background: #ffffff;
    border-radius: 7px;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.activity-image-preview {
    display: none;
    margin-top: 15px;
    padding: 10px;
    border: 1px solid #dde3eb;
    border-radius: 12px;
    background: #ffffff;
}

.activity-image-preview.show {
    display: block;
}

.activity-image-preview img {
    display: block;
    width: 100%;
    max-height: 320px;
    object-fit: contain;
    border-radius: 8px;
}


/* ========================================
   มือถือ
======================================== */

@media (max-width: 700px) {

    .thai-date-row,
    .thai-registration-date {
        grid-template-columns: 1fr;
    }

    .thai-time-row,
    .thai-registration-time {
        flex-wrap: wrap;
    }

    .thai-time-row select,
    .thai-registration-time select {
        width: 90px;
        flex-basis: 90px;
    }
}

</style>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const form =
            document.getElementById(
                'activityForm'
            );


        // =========================================
        // ดึงค่า Element
        // =========================================

        function value(id) {

            const element =
                document.getElementById(id);

            return element
                ? element.value
                : '';
        }


        function pad(number) {

            return String(number)
                .padStart(2, '0');
        }


        // =========================================
        // พ.ศ. -> ค.ศ.
        // =========================================

        function buddhistToGregorian(
            year
        ) {

            return (
                parseInt(year, 10) -
                543
            );
        }


        // =========================================
        // สร้างวันที่ yyyy-mm-dd
        // =========================================

        function buildDate(
            dayId,
            monthId,
            yearId
        ) {

            const day =
                value(dayId);

            const month =
                value(monthId);

            const thaiYear =
                value(yearId);


            if (
                day === '' ||
                month === '' ||
                thaiYear === ''
            ) {

                return '';
            }


            const year =
                buddhistToGregorian(
                    thaiYear
                );


            return (
                year +
                '-' +
                pad(month) +
                '-' +
                pad(day)
            );
        }


        // =========================================
        // สร้างเวลา HH:MM
        // =========================================

        function buildTime(
            hourId,
            minuteId
        ) {

            const hour =
                value(hourId);

            const minute =
                value(minuteId);


            if (
                hour === '' ||
                minute === ''
            ) {

                return '';
            }


            return (
                pad(hour) +
                ':' +
                pad(minute)
            );
        }


        // =========================================
        // อัปเดต Hidden
        // =========================================

        function updateHiddenFields() {


            document.getElementById(
                'activity_date'
            ).value =
                buildDate(
                    'activity_day',
                    'activity_month',
                    'activity_year'
                );


            document.getElementById(
                'start_time'
            ).value =
                buildTime(
                    'start_hour',
                    'start_minute'
                );


            document.getElementById(
                'end_time'
            ).value =
                buildTime(
                    'end_hour',
                    'end_minute'
                );


            const openDate =
                buildDate(
                    'open_day',
                    'open_month',
                    'open_year'
                );

            const openTime =
                buildTime(
                    'open_hour',
                    'open_minute'
                );


            document.getElementById(
                'registration_open'
            ).value =
                (
                    openDate !== '' &&
                    openTime !== ''
                )
                ? openDate +
                    'T' +
                    openTime
                : '';


            const closeDate =
                buildDate(
                    'close_day',
                    'close_month',
                    'close_year'
                );

            const closeTime =
                buildTime(
                    'close_hour',
                    'close_minute'
                );


            document.getElementById(
                'registration_close'
            ).value =
                (
                    closeDate !== '' &&
                    closeTime !== ''
                )
                ? closeDate +
                    'T' +
                    closeTime
                : '';
        }


        // =========================================
        // ตรวจวันที่จริง
        // =========================================

        function isValidDate(
            dateText
        ) {

            if (dateText === '') {
                return false;
            }


            const parts =
                dateText.split('-');


            if (parts.length !== 3) {
                return false;
            }


            const year =
                parseInt(parts[0], 10);

            const month =
                parseInt(parts[1], 10);

            const day =
                parseInt(parts[2], 10);


            const date =
                new Date(
                    year,
                    month - 1,
                    day
                );


            return (
                date.getFullYear() === year &&
                date.getMonth() ===
                    month - 1 &&
                date.getDate() === day
            );
        }


        // =========================================
        // ก่อน Submit
        // =========================================

        form.addEventListener(
            'submit',
            function (event) {


                updateHiddenFields();


                const activityDate =
                    document.getElementById(
                        'activity_date'
                    ).value;


                const startTime =
                    document.getElementById(
                        'start_time'
                    ).value;


                const endTime =
                    document.getElementById(
                        'end_time'
                    ).value;


                const registrationOpen =
                    document.getElementById(
                        'registration_open'
                    ).value;


                const registrationClose =
                    document.getElementById(
                        'registration_close'
                    ).value;


                // วันที่กิจกรรม
                if (
                    !isValidDate(
                        activityDate
                    )
                ) {

                    event.preventDefault();

                    alert(
                        'กรุณาเลือกวันที่จัดกิจกรรมให้ถูกต้อง'
                    );

                    return;
                }


                // เวลา
                if (
                    startTime === '' ||
                    endTime === ''
                ) {

                    event.preventDefault();

                    alert(
                        'กรุณาเลือกเวลาเริ่มและเวลาสิ้นสุด'
                    );

                    return;
                }


                if (
                    endTime <= startTime
                ) {

                    event.preventDefault();

                    alert(
                        'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม'
                    );

                    return;
                }


                // วันรับสมัคร
                if (
                    registrationOpen === '' ||
                    registrationClose === ''
                ) {

                    event.preventDefault();

                    alert(
                        'กรุณาเลือกวันและเวลารับสมัครให้ครบ'
                    );

                    return;
                }


                const openDate =
                    registrationOpen
                        .substring(0, 10);


                const closeDate =
                    registrationClose
                        .substring(0, 10);


                if (
                    !isValidDate(openDate) ||
                    !isValidDate(closeDate)
                ) {

                    event.preventDefault();

                    alert(
                        'วันเปิดหรือวันปิดรับสมัครไม่ถูกต้อง'
                    );

                    return;
                }


                if (
                    registrationClose <=
                    registrationOpen
                ) {

                    event.preventDefault();

                    alert(
                        'วันปิดรับสมัครต้องอยู่หลังวันเปิดรับสมัคร'
                    );

                    return;
                }

            }
        );


        // =========================================
        // Preview รูป
        // =========================================

        const coverImage =
            document.getElementById(
                'coverImage'
            );


        const coverFileName =
            document.getElementById(
                'coverFileName'
            );


        const previewBox =
            document.getElementById(
                'imagePreviewBox'
            );


        const preview =
            document.getElementById(
                'imagePreview'
            );


        coverImage.addEventListener(
            'change',
            function () {


                const file =
                    this.files[0];


                if (!file) {

                    coverFileName.textContent =
                        'ยังไม่ได้เลือกรูป';

                    previewBox.classList.remove(
                        'show'
                    );

                    preview.src = '';

                    return;
                }


                if (
                    file.size >
                    5 * 1024 * 1024
                ) {

                    alert(
                        'รูปหน้าปกต้องมีขนาดไม่เกิน 5 MB'
                    );

                    this.value = '';

                    coverFileName.textContent =
                        'ยังไม่ได้เลือกรูป';

                    previewBox.classList.remove(
                        'show'
                    );

                    return;
                }


                coverFileName.textContent =
                    file.name;


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        preview.src =
                            event.target.result;

                        previewBox.classList.add(
                            'show'
                        );
                    };


                reader.readAsDataURL(
                    file
                );

            }
        );


        // =========================================
        // ไฟล์แนบ
        // =========================================

        const attachments =
            document.getElementById(
                'attachments'
            );


        const attachmentNames =
            document.getElementById(
                'attachmentNames'
            );


        attachments.addEventListener(
            'change',
            function () {


                if (
                    !this.files ||
                    this.files.length === 0
                ) {

                    attachmentNames.textContent =
                        'ยังไม่ได้เลือกไฟล์';

                    return;
                }


                const names = [];


                for (
                    let i = 0;
                    i < this.files.length;
                    i++
                ) {

                    names.push(
                        this.files[i].name
                    );
                }


                attachmentNames.textContent =
                    names.join(', ');

            }
        );


    }
);

</script>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
