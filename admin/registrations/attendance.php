<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// attendance.php
// หน้านี้ใช้สำหรับให้ผู้ดูแลระบบเช็กชื่อผู้สมัครเข้าร่วมกิจกรรม

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'เช็กชื่อกิจกรรม';


// =========================================
// ฟังก์ชัน Escape
// =========================================

function attendE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================
// วันที่ไทย
// =========================================

function attendThaiDate(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

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

    return
        (int)date('d', $timestamp)
        . ' '
        . $months[(int)date('m', $timestamp)]
        . ' '
        . ((int)date('Y', $timestamp) + 543);
}


// =========================================
// เวลา
// =========================================

function attendTime(
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
        );

    if (!empty($end)) {

        $text .=
            ' - ' .
            date(
                'H:i',
                strtotime($end)
            );
    }

    return $text . ' น.';
}


// =========================================
// รับ activity_id
// =========================================

$activityId =
    isset($_GET['activity_id'])
    ? (int)$_GET['activity_id']
    : 0;


// =========================================
// ไม่มี ID ให้กลับ
// =========================================

if ($activityId <= 0) {

    header(
        'Location: index.php'
    );

    exit;
}


// =========================================
// ดึงข้อมูลกิจกรรม
// =========================================

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        activity_date,
        start_time,
        end_time,
        location

    FROM activities

    WHERE id = ?

    LIMIT 1
");

$stmt->execute([
    $activityId
]);

$activity =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$activity) {

    header(
        'Location: index.php'
    );

    exit;
}


// =========================================
// ดึงเฉพาะผู้ที่ยังลงทะเบียนอยู่
// พร้อมข้อมูลเช็กชื่อเดิม
// =========================================

$stmt = $pdo->prepare("
    SELECT

        ar.user_id,

        u.first_name,
        u.last_name,
        u.email,

        m.student_id,
        m.admission_year,
        m.faculty,
        m.major,
        m.year_level,
        m.member_status,

        aa.id AS attendance_id,
        aa.attendance_status,
        aa.check_in_time,
        aa.check_out_time,
        aa.note

    FROM activity_registrations ar

    INNER JOIN users u
        ON u.id = ar.user_id

    LEFT JOIN members m
        ON m.user_id = u.id

    LEFT JOIN activity_attendance aa
        ON aa.activity_id = ar.activity_id
        AND aa.user_id = ar.user_id

    WHERE ar.activity_id = ?

    AND ar.registration_status =
        'registered'

    ORDER BY
        u.first_name ASC,
        u.last_name ASC
");

$stmt->execute([
    $activityId
]);

$participants =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

foreach ($participants as &$participant) {
    $memberStatus = getAutomaticMemberStatus($participant);
    $participant['display_year_level'] = $memberStatus['year_level'];
}
unset($participant);

$attendanceStatusLabels = [
    'present' => 'มา',
    'late' => 'สาย',
    'absent' => 'ไม่มา'
];


// =========================================
// นับผู้สมัครจริงจากฐานข้อมูล
// =========================================

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM activity_registrations
    WHERE activity_id = ?
      AND registration_status = 'registered'
");
$countStmt->execute([$activityId]);
$participantCount = (int)$countStmt->fetchColumn();


// =========================================
// ข้อความแจ้งเตือน
// =========================================

$success =
    $_SESSION['attendance_success']
    ?? '';

$error =
    $_SESSION['attendance_error']
    ?? '';

unset(
    $_SESSION['attendance_success'],
    $_SESSION['attendance_error']
);


// =========================================
// CSRF
// =========================================

if (
    empty(
        $_SESSION['attendance_csrf']
    )
) {

    $_SESSION['attendance_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<?php if (($_GET['print'] ?? '') === '1'): ?>
    <script>
    window.addEventListener('load', function () {
        window.print();
    });
    </script>
<?php endif; ?>


<section class="panel full-panel">

    <div class="attendance-print-sheet">

        <header class="attendance-print-header">
            <img src="<?= BC_BASE_PATH ?>/assets/images/logo.png" alt="ชมรมบิตคอม">
            <div>
                <strong>ชมรมบิตคอม</strong>
                <span>คณะการบัญชีและการจัดการ</span>
                <span>มหาวิทยาลัยมหาสารคาม</span>
            </div>
        </header>

        <h1>รายชื่อผู้สมัครกิจกรรม</h1>

        <div class="attendance-print-meta">
            <p><strong>ชื่อกิจกรรม:</strong> <?= attendE($activity['title']) ?></p>
            <p><strong>วันที่จัดกิจกรรม:</strong> <?= attendE(attendThaiDate($activity['activity_date'])) ?></p>
            <p><strong>จำนวนผู้สมัครทั้งหมด:</strong> <?= number_format($participantCount) ?> คน</p>
        </div>

        <table class="attendance-print-table">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>รหัสนิสิต</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>คณะ</th>
                    <th>สาขา</th>
                    <th>ชั้นปี</th>
                    <th>สถานะ</th>
                    <th>เวลาเช็กเข้า</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($participants): ?>
                    <?php foreach ($participants as $index => $person): ?>
                        <?php
                        $printStatus =
                            $attendanceStatusLabels[
                                $person['attendance_status'] ?? ''
                            ] ?? 'รอเช็กชื่อ';

                        $printCheckIn =
                            !empty($person['check_in_time'])
                                ? date('H:i', strtotime($person['check_in_time'])) . ' น.'
                                : '-';

                        $printNote =
                            isset($person['note']) &&
                            (string)$person['note'] !== ''
                                ? (string)$person['note']
                                : '-';
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= attendE($person['student_id'] ?: '-') ?></td>
                            <td><?= attendE(trim($person['first_name'] . ' ' . $person['last_name'])) ?></td>
                            <td><?= attendE($person['faculty'] ?: '-') ?></td>
                            <td><?= attendE($person['major'] ?: '-') ?></td>
                            <td><?= $person['display_year_level'] ? (int)$person['display_year_level'] : '-' ?></td>
                            <td><?= attendE($printStatus) ?></td>
                            <td><?= attendE($printCheckIn) ?></td>
                            <td><?= attendE($printNote) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9">ไม่มีผู้สมัครกิจกรรม</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="attendance-print-signature">
            <span>ลงชื่อ ........................................................ ผู้ตรวจสอบ</span>
            <span>วันที่ ........../........../..........</span>
        </div>

    </div>


    <!-- =================================
         HEADER
    ================================== -->

    <div class="panel-head attendance-screen">

        <div>

            <small>
                ATTENDANCE
            </small>

            <h3>
                เช็กชื่อผู้เข้าร่วมกิจกรรม
            </h3>

        </div>


        <div class="attendance-head-actions">
            <button type="button" class="primary-btn" id="printAttendanceButton">
                <i class="fa-solid fa-print"></i>
                พิมพ์รายชื่อผู้สมัคร
            </button>

            <a
                href="index.php?activity_id=<?= $activityId ?>"
                class="secondary-btn"
            >
                <i class="fa-solid fa-arrow-left"></i>
                กลับ
            </a>
        </div>

    </div>


    <!-- =================================
         ALERT
    ================================== -->

    <?php if ($success): ?>

        <div class="attendance-alert success attendance-screen">

            <i class="fa-solid fa-circle-check"></i>

            <?= attendE($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="attendance-alert error attendance-screen">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= attendE($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================
         ข้อมูลกิจกรรม
    ================================== -->

    <div class="attendance-activity-card attendance-screen">


        <div class="attendance-activity-icon">

            <i class="fa-regular fa-calendar-check"></i>

        </div>


        <div class="attendance-activity-main">

            <span>
                กิจกรรม
            </span>

            <h2>

                <?= attendE(
                    $activity['title']
                ) ?>

            </h2>


            <div class="attendance-meta">


                <span>

                    <i class="fa-regular fa-calendar"></i>

                    <?= attendE(
                        attendThaiDate(
                            $activity['activity_date']
                        )
                    ) ?>

                </span>


                <span>

                    <i class="fa-regular fa-clock"></i>

                    <?= attendE(
                        attendTime(
                            $activity['start_time'],
                            $activity['end_time']
                        )
                    ) ?>

                </span>


                <span>

                    <i class="fa-solid fa-location-dot"></i>

                    <?= attendE(
                        $activity['location']
                        ?: '-'
                    ) ?>

                </span>


            </div>

        </div>


        <div class="attendance-count">

            <span>
                ผู้สมัคร
            </span>

            <strong>
                <?= number_format($participantCount) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


    </div>


    <!-- =================================
         รายชื่อ
    ================================== -->

    <?php if (!empty($participants)): ?>


        <form
            action="attendance_save.php"
            method="POST"
            id="attendanceForm"
            class="attendance-screen"
        >


            <input
                type="hidden"
                name="activity_id"
                value="<?= $activityId ?>"
            >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= attendE(
                    $_SESSION['attendance_csrf']
                ) ?>"
            >


            <div class="attendance-table-wrap">


                <table class="attendance-table">


                    <thead>

                        <tr>

                            <th>
                                ลำดับ
                            </th>

                            <th>
                                รหัสนิสิต
                            </th>

                            <th>
                                ชื่อ - นามสกุล
                            </th>

                            <th>
                                สถานะ
                            </th>

                            <th>
                                เวลาเช็กเข้า
                            </th>

                            <th>
                                หมายเหตุ
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $participants
                        as $index => $person
                    ): ?>


                        <?php

                        $currentStatus =
                            $person[
                                'attendance_status'
                            ] ?? '';

                        ?>


                        <tr>


                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <td>

                                <strong class="attendance-student">

                                    <?= attendE(
                                        $person[
                                            'student_id'
                                        ] ?: '-'
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <strong>

                                    <?= attendE(
                                        trim(
                                            $person[
                                                'first_name'
                                            ]
                                            . ' ' .
                                            $person[
                                                'last_name'
                                            ]
                                        )
                                    ) ?>

                                </strong>

                                <small>

                                    <?= attendE(
                                        $person[
                                            'email'
                                        ]
                                    ) ?>

                                </small>

                            </td>


                            <td>


                                <?php if ($currentStatus === ''): ?>
                                    <span class="attendance-pending">
                                        รอเช็กชื่อ
                                    </span>
                                <?php endif; ?>


                                <input
                                    type="hidden"
                                    name="user_id[]"
                                    value="<?= (int)$person['user_id'] ?>"
                                >

                                <?php if (!empty($person['check_out_time'])): ?>
                                    <input
                                        type="hidden"
                                        name="checkout[<?= (int)$person['user_id'] ?>]"
                                        value="1"
                                    >
                                <?php endif; ?>


                                <div class="attendance-status-options">


                                    <!-- มา -->

                                    <label
                                        class="attendance-option present"
                                    >

                                        <input
                                            type="radio"
                                            name="status[<?= (int)$person['user_id'] ?>]"
                                            value="present"
                                            <?= $currentStatus === 'present'
                                                ? 'checked'
                                                : ''
                                            ?>
                                            required
                                        >

                                        <span>

                                            <i class="fa-solid fa-check"></i>

                                            มา

                                        </span>

                                    </label>


                                    <!-- สาย -->

                                    <label
                                        class="attendance-option late"
                                    >

                                        <input
                                            type="radio"
                                            name="status[<?= (int)$person['user_id'] ?>]"
                                            value="late"
                                            <?= $currentStatus === 'late'
                                                ? 'checked'
                                                : ''
                                            ?>
                                        >

                                        <span>

                                            <i class="fa-solid fa-clock"></i>

                                            สาย

                                        </span>

                                    </label>


                                    <!-- ไม่มา -->

                                    <label
                                        class="attendance-option absent"
                                    >

                                        <input
                                            type="radio"
                                            name="status[<?= (int)$person['user_id'] ?>]"
                                            value="absent"
                                            <?= $currentStatus === 'absent'
                                                ? 'checked'
                                                : ''
                                            ?>
                                        >

                                        <span>

                                            <i class="fa-solid fa-xmark"></i>

                                            ไม่มา

                                        </span>

                                    </label>


                                </div>


                            </td>


                            <!-- เช็กเข้า -->

                            <td>

                                <?php if (
                                    !empty(
                                        $person[
                                            'check_in_time'
                                        ]
                                    )
                                ): ?>

                                    <span class="attendance-time">

                                        <?= date(
                                            'H:i',
                                            strtotime(
                                                $person[
                                                    'check_in_time'
                                                ]
                                            )
                                        ) ?>

                                        น.

                                    </span>

                                <?php else: ?>

                                    <span class="attendance-muted">
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- หมายเหตุ -->

                            <td>

                                <input
                                    type="text"
                                    name="note[<?= (int)$person['user_id'] ?>]"
                                    value="<?= attendE(
                                        $person[
                                            'note'
                                        ] ?? ''
                                    ) ?>"
                                    class="attendance-note"
                                    placeholder="หมายเหตุ"
                                    maxlength="255"
                                >

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


            <!-- =================================
                 ปุ่ม
            ================================== -->

            <div class="attendance-actions">


                <button
                    type="button"
                    class="secondary-btn"
                    id="markAllPresentButton"
                >

                    <i class="fa-solid fa-check-double"></i>

                    มาทั้งหมด

                </button>


                <a
                    href="index.php?activity_id=<?= $activityId ?>"
                    class="secondary-btn"
                >

                    ยกเลิก

                </a>


                <button
                    type="submit"
                    class="primary-btn"
                >

                    <i class="fa-regular fa-floppy-disk"></i>

                    บันทึกการเช็กชื่อ

                </button>


            </div>


        </form>


    <?php else: ?>


        <div class="attendance-empty attendance-screen">

            <i class="fa-solid fa-users-slash"></i>

            <h3>
                ยังไม่มีผู้สมัครกิจกรรม
            </h3>

            <p>
                ต้องมีผู้ลงทะเบียนกิจกรรมก่อน
                จึงจะสามารถเช็กชื่อได้
            </p>

        </div>


    <?php endif; ?>


</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('attendanceForm');
    const printButton = document.getElementById('printAttendanceButton');
    const markAllButton = document.getElementById('markAllPresentButton');
    let formChanged = false;

    if (form) {
        form.addEventListener('change', function () {
            formChanged = true;
        });

        form.addEventListener('input', function () {
            formChanged = true;
        });
    }

    if (markAllButton && form) {
        markAllButton.addEventListener('click', function () {
            let changed = false;

            form.querySelectorAll(
                'input[type="radio"][value="present"]'
            ).forEach(function (radio) {
                if (!radio.checked) {
                    radio.checked = true;
                    changed = true;
                }
            });

            if (changed) {
                formChanged = true;
            }
        });
    }

    if (printButton) {
        printButton.addEventListener('click', function () {
            if (formChanged) {
                window.alert(
                    'มีข้อมูลเช็กชื่อที่ยังไม่ได้บันทึก กรุณากดบันทึกการเช็กชื่อก่อนพิมพ์รายชื่อ'
                );
                return;
            }

            window.print();
        });
    }
});
</script>


<style>

.attendance-print-sheet {
    display: none;
}

.attendance-head-actions {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

/* ========================================
   ALERT
======================================== */

.attendance-alert {
    margin-bottom: 18px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 10px;
    font-size: 12px;
}

.attendance-alert.success {
    color: #17643d;
    background: #e8f7ef;
    border: 1px solid #b8e5cc;
}

.attendance-alert.error {
    color: #a52b32;
    background: #fff0f1;
    border: 1px solid #ffcbd0;
}


/* ========================================
   ACTIVITY
======================================== */

.attendance-activity-card {
    margin-bottom: 22px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 17px;
    background: #f8fafc;
    border: 1px solid #e0e6ee;
    border-radius: 14px;
}

.attendance-activity-icon {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    flex: 0 0 54px;
    color: #1762c6;
    background: #e8f2ff;
    border-radius: 13px;
    font-size: 21px;
}

.attendance-activity-main {
    flex: 1;
}

.attendance-activity-main > span {
    color: #8a95a5;
    font-size: 10px;
}

.attendance-activity-main h2 {
    margin: 3px 0 10px;
    color: #18345e;
    font-size: 19px;
}

.attendance-meta {
    display: flex;
    align-items: center;
    gap: 17px;
    flex-wrap: wrap;
}

.attendance-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #65758b;
    font-size: 11px;
}

.attendance-meta i {
    color: #2468c9;
}

.attendance-count {
    min-width: 100px;
    padding: 12px 18px;
    text-align: center;
    background: #ffffff;
    border-radius: 10px;
}

.attendance-count span,
.attendance-count small {
    display: block;
    color: #8792a3;
    font-size: 9px;
}

.attendance-count strong {
    display: block;
    color: #18345e;
    font-size: 25px;
}


/* ========================================
   TABLE
======================================== */

.attendance-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e1e6ed;
    border-radius: 12px;
}

.attendance-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
    background: #ffffff;
    font-size: 11px;
}

.attendance-table th {
    padding: 13px;
    color: #66758b;
    background: #f6f8fb;
    text-align: left;
    font-weight: 600;
}

.attendance-table td {
    padding: 14px 12px;
    color: #34445c;
    border-top: 1px solid #edf0f4;
    vertical-align: middle;
}

.attendance-table td strong,
.attendance-table td small {
    display: block;
}

.attendance-table td small {
    margin-top: 3px;
    color: #8b95a4;
    font-size: 9px;
}

.attendance-student {
    color: #18345e;
}


/* ========================================
   STATUS BUTTON
======================================== */

.attendance-status-options {
    display: flex;
    align-items: center;
    gap: 5px;
}

.attendance-option input {
    display: none;
}

.attendance-pending {
    margin-bottom: 6px;
    padding: 4px 7px;
    display: inline-flex;
    color: #6f7f92;
    background: #f0f3f7;
    border-radius: 999px;
    font-size: 8px;
    font-weight: 600;
}

.attendance-option span {
    min-height: 31px;
    padding: 6px 9px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #dde3eb;
    border-radius: 7px;
    background: #ffffff;
    color: #718096;
    cursor: pointer;
    white-space: nowrap;
    font-size: 9px;
    font-weight: 600;
}

.attendance-option.present input:checked + span {
    color: #17643d;
    background: #e4f7ed;
    border-color: #b2e3c7;
}

.attendance-option.late input:checked + span {
    color: #986515;
    background: #fff4d8;
    border-color: #efd79d;
}

.attendance-option.absent input:checked + span {
    color: #a3343e;
    background: #ffe9eb;
    border-color: #efb9be;
}


/* ========================================
   TIME
======================================== */

.attendance-time {
    color: #17643d;
    font-weight: 600;
    white-space: nowrap;
}

.attendance-muted {
    color: #a0a8b5;
}


/* ========================================
   CHECKOUT
======================================== */

.checkout-checkbox {
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #607086;
    font-size: 9px;
    white-space: nowrap;
}

.checkout-checkbox input {
    accent-color: #1762c6;
}


/* ========================================
   NOTE
======================================== */

.attendance-note {
    min-width: 150px;
    width: 100%;
    min-height: 36px;
    padding: 7px 9px;
    border: 1px solid #dce2ea;
    border-radius: 7px;
    font-family: "Prompt", sans-serif;
    font-size: 10px;
    outline: none;
}

.attendance-note:focus {
    border-color: #2468c9;
}


/* ========================================
   ACTION
======================================== */

.attendance-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 9px;
}


/* ========================================
   EMPTY
======================================== */

.attendance-empty {
    padding: 65px 20px;
    color: #7d899a;
    text-align: center;
    border: 1px dashed #d9dfe7;
    border-radius: 14px;
}

.attendance-empty i {
    margin-bottom: 12px;
    color: #a8b1bf;
    font-size: 30px;
}

.attendance-empty h3 {
    margin: 0 0 5px;
    color: #40516c;
}

.attendance-empty p {
    margin: 0;
    font-size: 11px;
}


/* ========================================
   MOBILE
======================================== */

@media (max-width: 700px) {

    .attendance-activity-card {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .attendance-count {
        width: 100%;
    }

}


/* ========================================
   PRINT APPLICANT LIST
======================================== */

@media print {

    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
        color: #000000;
    }

    .admin-sidebar,
    .admin-topbar,
    .attendance-screen,
    .no-print {
        display: none !important;
    }

    .admin-main {
        margin-left: 0 !important;
    }

    .admin-content {
        max-width: none !important;
        padding: 0 !important;
    }

    .panel.full-panel {
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .attendance-print-sheet {
        display: block !important;
        width: 100%;
        font-family: "Prompt", sans-serif;
    }

    .attendance-print-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        padding-bottom: 12px;
        border-bottom: 2px solid #000000;
    }

    .attendance-print-header img {
        width: 60px;
        height: 60px;
        object-fit: contain;
    }

    .attendance-print-header strong,
    .attendance-print-header span {
        display: block;
        text-align: center;
    }

    .attendance-print-header strong {
        font-size: 17px;
    }

    .attendance-print-header span {
        font-size: 11px;
    }

    .attendance-print-sheet h1 {
        margin: 15px 0 12px;
        text-align: center;
        font-size: 18px;
    }

    .attendance-print-meta {
        margin-bottom: 12px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
        font-size: 10px;
    }

    .attendance-print-meta p {
        margin: 0;
    }

    .attendance-print-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 9px;
    }

    .attendance-print-table th,
    .attendance-print-table td {
        padding: 4px 3px;
        border: 1px solid #444444;
        overflow-wrap: anywhere;
        white-space: normal;
        vertical-align: top;
    }

    .attendance-print-table th {
        text-align: center;
        background: #eeeeee !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .attendance-print-table th:nth-child(1) { width: 5%; }
    .attendance-print-table th:nth-child(2) { width: 12%; }
    .attendance-print-table th:nth-child(3) { width: 18%; }
    .attendance-print-table th:nth-child(4) { width: 12%; }
    .attendance-print-table th:nth-child(5) { width: 13%; }
    .attendance-print-table th:nth-child(6) { width: 6%; }
    .attendance-print-table th:nth-child(7) { width: 10%; }
    .attendance-print-table th:nth-child(8) { width: 10%; }
    .attendance-print-table th:nth-child(9) { width: 14%; }

    .attendance-print-table td:first-child,
    .attendance-print-table td:nth-child(6),
    .attendance-print-table td:nth-child(7),
    .attendance-print-table td:nth-child(8) {
        text-align: center;
    }

    .attendance-box {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 1.5px solid #000000;
        background: #ffffff !important;
        box-sizing: border-box;
        vertical-align: middle;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .attendance-print-table tr {
        break-inside: avoid;
    }

    .attendance-print-table thead {
        display: table-header-group;
    }

    .attendance-print-signature {
        margin-top: 25px;
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 20px;
        font-size: 10px;
        break-inside: avoid;
    }
}

</style>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
