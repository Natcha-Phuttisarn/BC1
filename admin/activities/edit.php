<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// edit.php
// หน้านี้ใช้สำหรับแก้ไขข้อมูลกิจกรรมที่เพิ่มหรือเผยแพร่ไปแล้ว

require_once __DIR__ . '/../includes/admin_auth.php';

$activityEditCsrf = $_SESSION['admin_activity_edit_csrf'] ?? '';

if (
    !is_string($activityEditCsrf) ||
    preg_match('/\A[a-f0-9]{64}\z/D', $activityEditCsrf) !== 1
) {
    $activityEditCsrf = bin2hex(random_bytes(32));
    $_SESSION['admin_activity_edit_csrf'] = $activityEditCsrf;
}

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'แก้ไขกิจกรรม';

$activityId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($activityId <= 0) {
    $_SESSION['activity_error'] = 'ไม่พบรหัสกิจกรรม';
    header('Location: index.php');
    exit;
}


// =========================================
// ดึงข้อมูลกิจกรรม
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM activities
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$activityId]);

$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    $_SESSION['activity_error'] = 'ไม่พบกิจกรรมที่ต้องการแก้ไข';
    header('Location: index.php');
    exit;
}


// =========================================
// ดึงไฟล์แนบเดิม
// =========================================

$fileStmt = $pdo->prepare("
    SELECT *
    FROM activity_files
    WHERE activity_id = ?
    ORDER BY uploaded_at ASC
");

$fileStmt->execute([$activityId]);

$activityFiles = $fileStmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================
// Error / ข้อมูลเดิม
// =========================================

$error = $_SESSION['activity_error'] ?? '';
$old = $_SESSION['activity_edit_old'] ?? [];

unset(
    $_SESSION['activity_error'],
    $_SESSION['activity_edit_old']
);

$form = array_merge($activity, $old);


// =========================================
// Helper
// =========================================

function editE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function editDateParts(?string $value): array
{
    if (empty($value)) {
        return [
            'day' => '',
            'month' => '',
            'year' => ''
        ];
    }

    $time = strtotime($value);

    if ($time === false) {
        return [
            'day' => '',
            'month' => '',
            'year' => ''
        ];
    }

    return [
        'day' => date('d', $time),
        'month' => date('m', $time),
        'year' => (int)date('Y', $time) + 543
    ];
}


function editTimeParts(?string $value): array
{
    if (empty($value)) {
        return [
            'hour' => '',
            'minute' => ''
        ];
    }

    $time = strtotime($value);

    if ($time === false) {
        return [
            'hour' => '',
            'minute' => ''
        ];
    }

    return [
        'hour' => date('H', $time),
        'minute' => date('i', $time)
    ];
}


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

$currentThaiYear = (int)date('Y') + 543;


// =========================================
// แยกวันเวลา
// =========================================

$activityDate = editDateParts(
    $form['activity_date'] ?? ''
);

$startTime = editTimeParts(
    $form['start_time'] ?? ''
);

$endTime = editTimeParts(
    $form['end_time'] ?? ''
);

$openDate = editDateParts(
    $form['registration_open'] ?? ''
);

$openTime = editTimeParts(
    $form['registration_open'] ?? ''
);

$closeDate = editDateParts(
    $form['registration_close'] ?? ''
);

$closeTime = editTimeParts(
    $form['registration_close'] ?? ''
);


$coverImage = !empty($activity['cover_image'])
    ? ('' . BC_BASE_PATH . '/uploads/activities/covers/') . $activity['cover_image']
    : ('' . BC_BASE_PATH . '/assets/images/logo.png');


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>


<section class="panel full-panel">

    <div class="panel-head">

        <div>
            <small>ACTIVITIES</small>
            <h3>แก้ไขกิจกรรม</h3>
        </div>

        <a
            href="index.php"
            class="secondary-btn"
        >
            <i class="fa-solid fa-arrow-left"></i>
            กลับ
        </a>

    </div>


    <?php if ($error): ?>

        <div class="form-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= editE($error) ?>

        </div>

    <?php endif; ?>


    <form
        action="update.php"
        method="POST"
        enctype="multipart/form-data"
        class="activity-form"
        id="editActivityForm"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($activityEditCsrf, ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= $activityId ?>"
        >

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
                    <h4>ข้อมูลกิจกรรม</h4>
                    <p>แก้ไขข้อมูลกิจกรรมที่โพสต์ไว้แล้ว</p>
                </div>

            </div>


            <div class="form-group full">

                <label>
                    ชื่อกิจกรรม <span>*</span>
                </label>

                <input
                    type="text"
                    name="title"
                    value="<?= editE($form['title'] ?? '') ?>"
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
                    maxlength="255"
                    value="<?= editE($form['short_description'] ?? '') ?>"
                >

            </div>


            <div class="form-group full">

                <label>
                    รายละเอียดกิจกรรม <span>*</span>
                </label>

                <textarea
                    name="description"
                    rows="7"
                    required
                ><?= editE($form['description'] ?? '') ?></textarea>

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
                    <h4>วัน เวลา และสถานที่</h4>
                    <p>แก้ไขวัน เวลา และสถานที่จัดกิจกรรม</p>
                </div>

            </div>


            <div class="form-group full">

                <label>
                    วันที่จัดกิจกรรม <span>*</span>
                </label>

                <div class="edit-thai-date">

                    <select id="activity_day" required>

                        <option value="">วัน</option>

                        <?php for ($d = 1; $d <= 31; $d++): ?>

                            <?php $value = sprintf('%02d', $d); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $activityDate['day'] === $value ? 'selected' : '' ?>
                            >
                                <?= $d ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select id="activity_month" required>

                        <option value="">เดือน</option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $value = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $activityDate['month'] === $value ? 'selected' : '' ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select id="activity_year" required>

                        <option value="">พ.ศ.</option>

                        <?php for (
                            $year = $currentThaiYear - 2;
                            $year <= $currentThaiYear + 6;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$activityDate['year'] === $year ? 'selected' : '' ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        สถานที่ <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="<?= editE($form['location'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        เวลาเริ่ม <span>*</span>
                    </label>

                    <div class="edit-thai-time">

                        <select id="start_hour" required>

                            <option value="">ชม.</option>

                            <?php for ($h = 0; $h <= 23; $h++): ?>

                                <?php $value = sprintf('%02d', $h); ?>

                                <option
                                    value="<?= $value ?>"
                                    <?= $startTime['hour'] === $value ? 'selected' : '' ?>
                                >
                                    <?= $value ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                        <b>:</b>

                        <select id="start_minute" required>

                            <option value="">นาที</option>

                            <?php for ($m = 0; $m <= 59; $m++): ?>

                                <?php $value = sprintf('%02d', $m); ?>

                                <option
                                    value="<?= $value ?>"
                                    <?= $startTime['minute'] === $value ? 'selected' : '' ?>
                                >
                                    <?= $value ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                        <span>น.</span>

                    </div>

                </div>


                <div class="form-group">

                    <label>
                        เวลาสิ้นสุด <span>*</span>
                    </label>

                    <div class="edit-thai-time">

                        <select id="end_hour" required>

                            <option value="">ชม.</option>

                            <?php for ($h = 0; $h <= 23; $h++): ?>

                                <?php $value = sprintf('%02d', $h); ?>

                                <option
                                    value="<?= $value ?>"
                                    <?= $endTime['hour'] === $value ? 'selected' : '' ?>
                                >
                                    <?= $value ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                        <b>:</b>

                        <select id="end_minute" required>

                            <option value="">นาที</option>

                            <?php for ($m = 0; $m <= 59; $m++): ?>

                                <?php $value = sprintf('%02d', $m); ?>

                                <option
                                    value="<?= $value ?>"
                                    <?= $endTime['minute'] === $value ? 'selected' : '' ?>
                                >
                                    <?= $value ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                        <span>น.</span>

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
                    <h4>การรับสมัคร</h4>
                    <p>แก้ไขช่วงเวลารับสมัครและจำนวนผู้เข้าร่วม</p>
                </div>

            </div>


            <!-- เปิดรับสมัคร -->

            <div class="edit-registration-box">

                <label class="edit-registration-title">
                    เปิดรับสมัคร <span>*</span>
                </label>


                <div class="edit-thai-date">

                    <select id="open_day" required>

                        <option value="">วัน</option>

                        <?php for ($d = 1; $d <= 31; $d++): ?>

                            <?php $value = sprintf('%02d', $d); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $openDate['day'] === $value ? 'selected' : '' ?>
                            >
                                <?= $d ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select id="open_month" required>

                        <option value="">เดือน</option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $value = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $openDate['month'] === $value ? 'selected' : '' ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select id="open_year" required>

                        <option value="">พ.ศ.</option>

                        <?php for (
                            $year = $currentThaiYear - 2;
                            $year <= $currentThaiYear + 6;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$openDate['year'] === $year ? 'selected' : '' ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="edit-registration-time">

                    <span>เวลา</span>

                    <select id="open_hour" required>

                        <option value="">ชม.</option>

                        <?php for ($h = 0; $h <= 23; $h++): ?>

                            <?php $value = sprintf('%02d', $h); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $openTime['hour'] === $value ? 'selected' : '' ?>
                            >
                                <?= $value ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                    <b>:</b>

                    <select id="open_minute" required>

                        <option value="">นาที</option>

                        <?php for ($m = 0; $m <= 59; $m++): ?>

                            <?php $value = sprintf('%02d', $m); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $openTime['minute'] === $value ? 'selected' : '' ?>
                            >
                                <?= $value ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                    <span>น.</span>

                </div>

            </div>


            <!-- ปิดรับสมัคร -->

            <div class="edit-registration-box">

                <label class="edit-registration-title">
                    ปิดรับสมัคร <span>*</span>
                </label>


                <div class="edit-thai-date">

                    <select id="close_day" required>

                        <option value="">วัน</option>

                        <?php for ($d = 1; $d <= 31; $d++): ?>

                            <?php $value = sprintf('%02d', $d); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $closeDate['day'] === $value ? 'selected' : '' ?>
                            >
                                <?= $d ?>
                            </option>

                        <?php endfor; ?>

                    </select>


                    <select id="close_month" required>

                        <option value="">เดือน</option>

                        <?php foreach ($thaiMonths as $number => $name): ?>

                            <?php $value = sprintf('%02d', $number); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $closeDate['month'] === $value ? 'selected' : '' ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select id="close_year" required>

                        <option value="">พ.ศ.</option>

                        <?php for (
                            $year = $currentThaiYear - 2;
                            $year <= $currentThaiYear + 6;
                            $year++
                        ): ?>

                            <option
                                value="<?= $year ?>"
                                <?= (int)$closeDate['year'] === $year ? 'selected' : '' ?>
                            >
                                พ.ศ. <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="edit-registration-time">

                    <span>เวลา</span>

                    <select id="close_hour" required>

                        <option value="">ชม.</option>

                        <?php for ($h = 0; $h <= 23; $h++): ?>

                            <?php $value = sprintf('%02d', $h); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $closeTime['hour'] === $value ? 'selected' : '' ?>
                            >
                                <?= $value ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                    <b>:</b>

                    <select id="close_minute" required>

                        <option value="">นาที</option>

                        <?php for ($m = 0; $m <= 59; $m++): ?>

                            <?php $value = sprintf('%02d', $m); ?>

                            <option
                                value="<?= $value ?>"
                                <?= $closeTime['minute'] === $value ? 'selected' : '' ?>
                            >
                                <?= $value ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                    <span>น.</span>

                </div>

            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        จำนวนผู้เข้าร่วมสูงสุด
                    </label>

                    <input
                        type="number"
                        name="max_participants"
                        min="1"
                        value="<?= editE($form['max_participants'] ?? '') ?>"
                        placeholder="เช่น 50"
                    >

                    <small class="form-help">
                        หากไม่จำกัดจำนวน สามารถเว้นว่างได้
                    </small>

                </div>


                <div class="form-group">

                    <label>
                        สถานะกิจกรรม <span>*</span>
                    </label>

                    <select
                        name="publish_status"
                        required
                    >

                        <option
                            value="published"
                            <?= ($form['publish_status'] ?? '') === 'published'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            เผยแพร่
                        </option>

                        <option
                            value="draft"
                            <?= ($form['publish_status'] ?? '') === 'draft'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            ฉบับร่าง
                        </option>

                        <option
                            value="hidden"
                            <?= ($form['publish_status'] ?? '') === 'hidden'
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
                    <h4>รูปภาพและไฟล์แนบ</h4>
                    <p>เปลี่ยนรูปปกหรือเพิ่มไฟล์แนบเพิ่มเติม</p>
                </div>

            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        รูปหน้าปก
                    </label>

                    <div class="edit-cover">

                        <img
                            src="<?= editE($coverImage) ?>"
                            id="coverPreview"
                            alt="รูปกิจกรรม"
                        >

                    </div>

                    <input
                        type="file"
                        name="cover_image"
                        id="coverInput"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small class="form-help">
                        หากไม่เลือกรูปใหม่ จะใช้รูปเดิม
                    </small>

                </div>


                <div class="form-group">

                    <label>
                        เพิ่มไฟล์แนบ
                    </label>

                    <input
                        type="file"
                        name="attachments[]"
                        multiple
                    >


                    <?php if (!empty($activityFiles)): ?>

                        <div class="edit-existing-files">

                            <strong>
                                ไฟล์แนบปัจจุบัน
                            </strong>

                            <?php foreach ($activityFiles as $file): ?>

                                <a
                                    href="<?= BC_BASE_PATH ?>/download_activity_file.php?id=<?= (int)$file['id'] ?>"
                                    target="_blank"
                                >
                                    <i class="fa-solid fa-paperclip"></i>

                                    <?= editE(
                                        $file['original_name'] ?? 'ไฟล์แนบ'
                                    ) ?>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

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
                class="primary-btn"
            >
                <i class="fa-regular fa-floppy-disk"></i>
                บันทึกการแก้ไข
            </button>

        </div>

    </form>

</section>


<style>
.edit-thai-date {
    display: grid;
    grid-template-columns: 90px 1fr 140px;
    gap: 10px;
}

.edit-thai-date select,
.edit-thai-time select,
.edit-registration-time select {
    min-height: 48px;
    padding: 10px 12px;
    border: 1px solid #d9e1ec;
    border-radius: 10px;
    background: #ffffff;
    font-family: "Prompt", sans-serif;
    color: #263954;
}

.edit-thai-time,
.edit-registration-time {
    display: flex;
    align-items: center;
    gap: 7px;
}

.edit-thai-time select,
.edit-registration-time select {
    width: 95px;
    flex: 0 0 95px;
}

.edit-thai-time b,
.edit-registration-time b {
    color: #18345e;
    font-size: 19px;
}

.edit-thai-time span,
.edit-registration-time span {
    color: #6f7e92;
    font-size: 12px;
}

.edit-registration-box {
    margin-bottom: 20px;
    padding: 19px;
    border: 1px solid #e1e7ef;
    border-radius: 14px;
    background: #fbfcfe;
}

.edit-registration-title {
    display: block;
    margin-bottom: 12px;
    color: #18345e;
    font-size: 13px;
    font-weight: 600;
}

.edit-registration-title span {
    color: #e5484d;
}

.edit-registration-time {
    margin-top: 13px;
}

.edit-cover {
    margin-bottom: 12px;
    padding: 8px;
    border: 1px solid #e0e6ee;
    border-radius: 12px;
    background: #f7f9fc;
}

.edit-cover img {
    width: 100%;
    max-height: 300px;
    display: block;
    object-fit: contain;
    border-radius: 8px;
}

.edit-existing-files {
    margin-top: 15px;
    display: grid;
    gap: 7px;
}

.edit-existing-files > strong {
    color: #18345e;
    font-size: 12px;
}

.edit-existing-files a {
    padding: 9px 11px;
    display: flex;
    align-items: center;
    gap: 7px;
    color: #315c96;
    background: #f2f7fd;
    border-radius: 8px;
    font-size: 11px;
    text-decoration: none;
}

@media (max-width: 700px) {
    .edit-thai-date {
        grid-template-columns: 1fr;
    }
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const form =
        document.getElementById('editActivityForm');

    function value(id) {
        return document.getElementById(id).value;
    }

    function buildDate(dayId, monthId, yearId) {

        const day = value(dayId);
        const month = value(monthId);
        const thaiYear = value(yearId);

        if (
            day === '' ||
            month === '' ||
            thaiYear === ''
        ) {
            return '';
        }

        const year =
            parseInt(thaiYear, 10) - 543;

        return (
            year +
            '-' +
            month +
            '-' +
            day
        );
    }

    function buildTime(hourId, minuteId) {

        const hour = value(hourId);
        const minute = value(minuteId);

        if (
            hour === '' ||
            minute === ''
        ) {
            return '';
        }

        return hour + ':' + minute;
    }

    function updateHidden() {

        document.getElementById(
            'activity_date'
        ).value = buildDate(
            'activity_day',
            'activity_month',
            'activity_year'
        );

        document.getElementById(
            'start_time'
        ).value = buildTime(
            'start_hour',
            'start_minute'
        );

        document.getElementById(
            'end_time'
        ).value = buildTime(
            'end_hour',
            'end_minute'
        );

        const openDate = buildDate(
            'open_day',
            'open_month',
            'open_year'
        );

        const openTime = buildTime(
            'open_hour',
            'open_minute'
        );

        const closeDate = buildDate(
            'close_day',
            'close_month',
            'close_year'
        );

        const closeTime = buildTime(
            'close_hour',
            'close_minute'
        );

        document.getElementById(
            'registration_open'
        ).value =
            openDate && openTime
                ? openDate + 'T' + openTime
                : '';

        document.getElementById(
            'registration_close'
        ).value =
            closeDate && closeTime
                ? closeDate + 'T' + closeTime
                : '';
    }


    form.addEventListener('submit', function (event) {

        updateHidden();

        const start =
            document.getElementById('start_time').value;

        const end =
            document.getElementById('end_time').value;

        if (end <= start) {

            event.preventDefault();

            alert(
                'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม'
            );

            return;
        }

        const open =
            document.getElementById(
                'registration_open'
            ).value;

        const close =
            document.getElementById(
                'registration_close'
            ).value;

        if (close <= open) {

            event.preventDefault();

            alert(
                'วันปิดรับสมัครต้องอยู่หลังวันเปิดรับสมัคร'
            );
        }

    });


    const coverInput =
        document.getElementById('coverInput');

    const coverPreview =
        document.getElementById('coverPreview');


    coverInput.addEventListener(
        'change',
        function () {

            const file = this.files[0];

            if (!file) {
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

                return;
            }

            const reader =
                new FileReader();

            reader.onload =
                function (event) {
                    coverPreview.src =
                        event.target.result;
                };

            reader.readAsDataURL(file);
        }
    );

});
</script>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
