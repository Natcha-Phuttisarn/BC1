<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// admin/registrations/index.php
// หน้านี้ใช้สำหรับให้ผู้ดูแลระบบดูรายชื่อผู้สมัครเข้าร่วมกิจกรรม

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'ผู้สมัครกิจกรรม';

$registrationSuccess = $_SESSION['registration_success'] ?? '';
$registrationError = $_SESSION['registration_error'] ?? '';
unset($_SESSION['registration_success'], $_SESSION['registration_error']);


// =========================================
// ฟังก์ชัน Escape
// =========================================

function regE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================
// วันที่แบบไทย
// =========================================

function regThaiDate(?string $date): string
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

    $day = (int)date('d', $timestamp);

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
// เวลา
// =========================================

function regTime(
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

function regOptionalDateInteger(
    $value,
    int $minimum,
    int $maximum,
    bool &$invalid
): ?int
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        $invalid = true;
        return null;
    }

    $number = (int)$value;

    if ($number < $minimum || $number > $maximum) {
        $invalid = true;
        return null;
    }

    return $number;
}


// =========================================
// รับค่าค้นหา
// =========================================

$activityId =
    isset($_GET['activity_id'])
    ? (int)$_GET['activity_id']
    : 0;

$isPrintMode = (($_GET['print'] ?? '') === '1');

$search =
    trim($_GET['search'] ?? '');

$status =
    trim($_GET['status'] ?? '');

$dateFilterInvalid = false;
$filterDay = regOptionalDateInteger(
    $_GET['filter_day'] ?? '',
    1,
    31,
    $dateFilterInvalid
);
$filterMonth = regOptionalDateInteger(
    $_GET['filter_month'] ?? '',
    1,
    12,
    $dateFilterInvalid
);
$filterYear = regOptionalDateInteger(
    $_GET['filter_year'] ?? '',
    2443,
    3043,
    $dateFilterInvalid
);
$filterGregorianYear = $filterYear !== null
    ? $filterYear - 543
    : null;

if (
    !$dateFilterInvalid
    && $filterDay !== null
    && $filterMonth !== null
) {
    $validationYear = $filterGregorianYear ?? 2000;

    if (!checkdate($filterMonth, $filterDay, $validationYear)) {
        $dateFilterInvalid = true;
    }
}

$filterMaximumDay = 31;

if ($filterMonth !== null) {
    $dayOptionYear = $filterGregorianYear ?? 2000;
    $filterMaximumDay = (int)(new DateTimeImmutable(
        sprintf('%04d-%02d-01', $dayOptionYear, $filterMonth)
    ))->format('t');
}

$registrationDateFilterActive =
    $filterDay !== null
    || $filterMonth !== null
    || $filterYear !== null
    || $dateFilterInvalid;

$filterMonths = [
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

$registrationYearSql = "
    SELECT DISTINCT YEAR(registered_at) AS registration_year
    FROM activity_registrations
    WHERE registered_at IS NOT NULL
";
$registrationYearParams = [];

if ($activityId > 0) {
    $registrationYearSql .= ' AND activity_id = ?';
    $registrationYearParams[] = $activityId;
}

$registrationYearSql .= ' ORDER BY registration_year DESC';
$registrationYearStmt = $pdo->prepare($registrationYearSql);
$registrationYearStmt->execute($registrationYearParams);
$registrationYears = array_map(
    'intval',
    $registrationYearStmt->fetchAll(PDO::FETCH_COLUMN)
);

$allowedStatus = [
    '',
    'registered',
    'cancelled'
];

if (
    !in_array(
        $status,
        $allowedStatus,
        true
    )
) {
    $status = '';
}


// =========================================
// ดึงกิจกรรมทั้งหมดพร้อมจำนวนผู้สมัคร
// =========================================

$activityStmt =
    $pdo->query("
        SELECT
            a.id,
            a.title,
            a.activity_date,
            a.start_time,
            a.end_time,
            a.location,
            a.max_participants,
            a.publish_status,

            (
                SELECT COUNT(*)

                FROM activity_registrations ar

                WHERE ar.activity_id = a.id

                AND ar.registration_status =
                    'registered'

            ) AS registered_count,

            (
                SELECT COUNT(*)

                FROM activity_registrations ar2

                WHERE ar2.activity_id = a.id

            ) AS total_registration_rows

        FROM activities a

        ORDER BY
            a.activity_date DESC,
            a.id DESC
    ");

$activities =
    $activityStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// =========================================
// กิจกรรมที่เลือก
// =========================================

$selectedActivity = null;

if ($activityId > 0) {

    $selectedStmt =
        $pdo->prepare("
            SELECT
                id,
                title,
                activity_date,
                start_time,
                end_time,
                location,
                max_participants,
                publish_status

            FROM activities

            WHERE id = ?

            LIMIT 1
        ");

    $selectedStmt->execute([
        $activityId
    ]);

    $selectedActivity =
        $selectedStmt->fetch(
            PDO::FETCH_ASSOC
        );
}


// =========================================
// ดึงรายชื่อผู้สมัคร
// =========================================

$registrations = [];
$printRegistrations = [];

if ($selectedActivity) {

    $sql = "
        SELECT
            ar.id AS registration_id,
            ar.user_id,
            ar.registration_status,
            ar.cancel_reason AS cancel_reason_text,
            ar.registered_at,
            ar.cancelled_at AS cancelled_datetime,

            u.first_name,
            u.last_name,
            u.email,
            u.account_status,

            m.student_id,
            m.faculty,
            m.major,
            m.phone,
            m.member_status

        FROM activity_registrations ar

        INNER JOIN users u
            ON u.id = ar.user_id

        LEFT JOIN members m
            ON m.user_id = u.id

        WHERE ar.activity_id = ?
    ";

    $params = [
        $activityId
    ];


    // =====================================
    // กรองสถานะ
    // =====================================

    if ($status !== '') {

        $sql .= "
            AND ar.registration_status = ?
        ";

        $params[] = $status;
    }


    // =====================================
    // กรองวันที่สมัครกิจกรรมจริง
    // =====================================

    if ($dateFilterInvalid) {
        $sql .= "
            AND 1 = 0
        ";
    } else {
        if ($filterDay !== null) {
            $sql .= "
                AND DAY(ar.registered_at) = ?
            ";
            $params[] = $filterDay;
        }

        if ($filterMonth !== null) {
            $sql .= "
                AND MONTH(ar.registered_at) = ?
            ";
            $params[] = $filterMonth;
        }

        if ($filterGregorianYear !== null) {
            $sql .= "
                AND YEAR(ar.registered_at) = ?
            ";
            $params[] = $filterGregorianYear;
        }
    }


    // =====================================
    // ค้นหา
    // =====================================

    if ($search !== '') {

        $sql .= "
            AND (
                u.first_name LIKE ?
                OR u.last_name LIKE ?
                OR u.email LIKE ?
                OR m.student_id LIKE ?
                OR m.phone LIKE ?
            )
        ";

        $keyword =
            '%' . $search . '%';

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }


    $sql .= "
        ORDER BY

            CASE
                WHEN ar.registration_status =
                    'registered'
                THEN 1

                WHEN ar.registration_status =
                    'cancelled'
                THEN 2

                ELSE 3
            END,

            u.first_name ASC,
            u.last_name ASC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $registrations =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    if ($isPrintMode) {
        $printSql = "
            SELECT
                u.first_name,
                u.last_name,
                m.student_id,
                m.faculty,
                m.major
            FROM activity_registrations ar
            INNER JOIN users u ON u.id = ar.user_id
            LEFT JOIN members m ON m.user_id = u.id
            WHERE ar.activity_id = ?
                AND ar.registration_status = 'registered'
        ";
        $printParams = [$activityId];

        // Keep the currently selected status filter. A cancelled filter
        // intentionally yields no printable rows because only registered
        // applications are eligible for this document.
        if ($status !== '') {
            $printSql .= ' AND ar.registration_status = ?';
            $printParams[] = $status;
        }

        if ($dateFilterInvalid) {
            $printSql .= ' AND 1 = 0';
        } else {
            if ($filterDay !== null) {
                $printSql .= ' AND DAY(ar.registered_at) = ?';
                $printParams[] = $filterDay;
            }
            if ($filterMonth !== null) {
                $printSql .= ' AND MONTH(ar.registered_at) = ?';
                $printParams[] = $filterMonth;
            }
            if ($filterGregorianYear !== null) {
                $printSql .= ' AND YEAR(ar.registered_at) = ?';
                $printParams[] = $filterGregorianYear;
            }
        }

        if ($search !== '') {
            $printSql .= "
                AND (
                    u.first_name LIKE ?
                    OR u.last_name LIKE ?
                    OR u.email LIKE ?
                    OR m.student_id LIKE ?
                    OR m.phone LIKE ?
                )
            ";
            $keyword = '%' . $search . '%';
            array_push(
                $printParams,
                $keyword,
                $keyword,
                $keyword,
                $keyword,
                $keyword
            );
        }

        $printSql .= ' ORDER BY u.first_name ASC, u.last_name ASC';
        $printStmt = $pdo->prepare($printSql);
        $printStmt->execute($printParams);
        $printRegistrations = $printStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$printQuery = [
    'activity_id' => $activityId,
    'print' => 1,
];
foreach (['search', 'status', 'filter_day', 'filter_month', 'filter_year'] as $filterKey) {
    if (isset($_GET[$filterKey]) && (string)$_GET[$filterKey] !== '') {
        $printQuery[$filterKey] = (string)$_GET[$filterKey];
    }
}
$printUrl = 'index.php?' . http_build_query($printQuery);


// =========================================
// นับสถานะของกิจกรรมที่เลือก
// =========================================

$registeredCount = 0;
$cancelledCount = 0;

if ($selectedActivity) {

    $countStmt =
        $pdo->prepare("
            SELECT
                registration_status,
                COUNT(*) AS total

            FROM activity_registrations

            WHERE activity_id = ?

            GROUP BY registration_status
        ");

    $countStmt->execute([
        $activityId
    ]);

    $counts =
        $countStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($counts as $row) {

        if (
            $row['registration_status']
            === 'registered'
        ) {

            $registeredCount =
                (int)$row['total'];

        } elseif (
            $row['registration_status']
            === 'cancelled'
        ) {

            $cancelledCount =
                (int)$row['total'];
        }
    }
}


// =========================================
// Header / Sidebar
// =========================================

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<?php if ($isPrintMode && $selectedActivity): ?>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
<?php endif; ?>


<section class="panel full-panel">

    <?php if ($isPrintMode && $selectedActivity): ?>
        <div class="registration-print-sheet">
            <header class="registration-print-header">
                <img
                    class="registration-print-logo"
                    src="<?= BC_BASE_PATH ?>/assets/images/logo.png"
                    alt="Bizcomp Club"
                >
                <div class="registration-print-heading">
                    <p class="registration-print-club-name">ชมรมบิตคอม</p>
                    <p>คณะการบัญชีและการจัดการ</p>
                    <p>มหาวิทยาลัยมหาสารคาม</p>
                </div>
            </header>
            <h1 class="registration-print-title">รายชื่อผู้สมัครเข้าร่วมกิจกรรม</h1>
            <div class="registration-print-meta">
                <p class="registration-print-activity"><strong>ชื่อกิจกรรม</strong><span><?= regE($selectedActivity['title']) ?></span></p>
                <p><strong>วันที่จัดกิจกรรม</strong><span><?= regE(regThaiDate($selectedActivity['activity_date'])) ?></span></p>
                <p class="registration-print-location"><strong>สถานที่จัดกิจกรรม</strong><span><?= regE($selectedActivity['location'] ?: '-') ?></span></p>
            </div>
            <table class="registration-print-table">
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>รหัสนิสิต</th>
                        <th>คณะ</th>
                        <th>สาขา</th>
                        <th>ลายเซ็น</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($printRegistrations === []): ?>
                        <tr><td colspan="6" class="registration-print-empty">ไม่มีรายชื่อสำหรับพิมพ์</td></tr>
                    <?php else: ?>
                        <?php foreach ($printRegistrations as $printIndex => $printRegistration): ?>
                            <tr>
                                <td class="registration-print-number"><?= $printIndex + 1 ?></td>
                                <td><?= regE(trim(($printRegistration['first_name'] ?? '') . ' ' . ($printRegistration['last_name'] ?? '')) ?: '-') ?></td>
                                <td><?= regE($printRegistration['student_id'] ?: '-') ?></td>
                                <td><?= regE($printRegistration['faculty'] ?: '-') ?></td>
                                <td><?= regE($printRegistration['major'] ?: '-') ?></td>
                                <td class="registration-signature"></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <footer class="registration-print-signoff">
                <p class="registration-print-total">รวมผู้สมัครเข้าร่วมกิจกรรมทั้งหมด <?= count($printRegistrations) ?> คน</p>
                <div class="registration-print-signoff-fields">
                    <div class="registration-print-responsible">
                        <p>ผู้รับผิดชอบกิจกรรม <span class="registration-print-signoff-line"></span></p>
                        <p class="registration-print-signoff-paren">(<span class="registration-print-name-line"></span>)</p>
                    </div>
                    <p class="registration-print-date">วันที่ <span class="registration-print-date-line"></span> / <span class="registration-print-date-line"></span> / <span class="registration-print-date-line registration-print-year-line"></span></p>
                </div>
            </footer>
        </div>
    <?php endif; ?>


    <!-- =================================
         หัวข้อ
    ================================== -->

    <div class="panel-head">

        <div>

            <small>
                ACTIVITY REGISTRATIONS
            </small>

            <h3>
                ผู้สมัครกิจกรรม
            </h3>

            <p class="reg-subtitle">
                ดูจำนวนและรายชื่อผู้สมัครเข้าร่วมกิจกรรมแต่ละรายการ
            </p>

        </div>

    </div>

    <?php if ($registrationSuccess): ?>
        <div class="form-alert success">
            <i class="fa-solid fa-circle-check"></i>
            <?= regE($registrationSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($registrationError): ?>
        <div class="form-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= regE($registrationError) ?>
        </div>
    <?php endif; ?>


    <?php if (!$selectedActivity): ?>


        <!-- =================================================
             หน้ารวมกิจกรรม
        ================================================== -->

        <div class="reg-section-head">

            <div>

                <h4>
                    เลือกกิจกรรม
                </h4>

                <p>
                    กดดูรายชื่อผู้สมัครของกิจกรรมที่ต้องการ
                </p>

            </div>

        </div>


        <?php if (!empty($activities)): ?>


            <div class="reg-activity-grid">


                <?php foreach (
                    $activities
                    as $activity
                ): ?>


                    <?php

                    $registered =
                        (int)$activity[
                            'registered_count'
                        ];

                    $max =
                        !empty(
                            $activity[
                                'max_participants'
                            ]
                        )
                        ? (int)$activity[
                            'max_participants'
                        ]
                        : null;

                    ?>


                    <article class="reg-activity-card">


                        <div class="reg-card-top">

                            <div class="reg-card-icon">

                                <i class="fa-regular fa-calendar"></i>

                            </div>


                            <span
                                class="reg-publish-status <?= regE($activity['publish_status']) ?>"
                            >

                                <?php

                                if (
                                    $activity[
                                        'publish_status'
                                    ] === 'published'
                                ) {

                                    echo 'เผยแพร่';

                                } elseif (
                                    $activity[
                                        'publish_status'
                                    ] === 'hidden'
                                ) {

                                    echo 'ซ่อน';

                                } else {

                                    echo 'ฉบับร่าง';
                                }

                                ?>

                            </span>

                        </div>


                        <h3>

                            <?= regE(
                                $activity['title']
                            ) ?>

                        </h3>


                        <div class="reg-card-info">


                            <p>

                                <i class="fa-regular fa-calendar"></i>

                                <?= regE(
                                    regThaiDate(
                                        $activity[
                                            'activity_date'
                                        ]
                                    )
                                ) ?>

                            </p>


                            <p>

                                <i class="fa-regular fa-clock"></i>

                                <?= regE(
                                    regTime(
                                        $activity[
                                            'start_time'
                                        ],
                                        $activity[
                                            'end_time'
                                        ]
                                    )
                                ) ?>

                            </p>


                            <p>

                                <i class="fa-solid fa-location-dot"></i>

                                <?= regE(
                                    $activity[
                                        'location'
                                    ] ?: '-'
                                ) ?>

                            </p>


                        </div>


                        <div class="reg-count-box">


                            <div>

                                <span>
                                    ผู้สมัครปัจจุบัน
                                </span>

                                <strong>
                                    <?= $registered ?>
                                </strong>

                            </div>


                            <div class="reg-count-divider"></div>


                            <div>

                                <span>
                                    จำนวนรับ
                                </span>

                                <strong>

                                    <?php if (
                                        $max !== null
                                    ): ?>

                                        <?= $max ?>

                                    <?php else: ?>

                                        ∞

                                    <?php endif; ?>

                                </strong>

                            </div>


                        </div>


                        <?php if (
                            $max !== null
                        ): ?>


                            <?php

                            $percent =
                                $max > 0
                                ? min(
                                    100,
                                    ($registered / $max)
                                    * 100
                                )
                                : 0;

                            ?>


                            <div class="reg-progress">

                                <div
                                    class="reg-progress-bar"
                                    style="width: <?= $percent ?>%;"
                                ></div>

                            </div>


                            <small class="reg-capacity-text">

                                <?= $registered ?>
                                /
                                <?= $max ?>
                                คน

                            </small>


                        <?php endif; ?>


                        <div class="reg-card-actions">

                            <a
                                href="index.php?activity_id=<?= (int)$activity['id'] ?>"
                                class="reg-view-btn"
                            >

                                <i class="fa-solid fa-users"></i>

                                ดูรายชื่อผู้สมัคร

                            </a>


                            <a
                                href="report.php?activity_id=<?= (int)$activity['id'] ?>"
                                class="reg-report-btn"
                            >

                                <i class="fa-solid fa-chart-column"></i>

                                รายงานกิจกรรม

                            </a>

                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="reg-empty">

                <i class="fa-regular fa-calendar-xmark"></i>

                <h3>
                    ยังไม่มีกิจกรรม
                </h3>

                <p>
                    เมื่อเพิ่มกิจกรรมแล้ว
                    รายการจะปรากฏที่หน้านี้
                </p>

            </div>


        <?php endif; ?>


    <?php else: ?>


        <!-- =================================================
             รายชื่อผู้สมัคร
        ================================================== -->


        <div class="reg-selected-head">

            <div class="reg-selected-actions">
                <a
                    href="index.php"
                    class="secondary-btn"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    กลับไปกิจกรรมทั้งหมด
                </a>

                <a
                    href="attendance.php?activity_id=<?= (int)$activityId ?>"
                    class="secondary-btn"
                >
                    <i class="fa-solid fa-clipboard-check"></i>
                    เช็กชื่อ
                </a>

                <a
                    href="<?= regE($printUrl) ?>"
                    class="primary-btn"
                >
                    <i class="fa-solid fa-print"></i>
                    พิมพ์รายชื่อผู้สมัคร
                </a>
            </div>


            <div class="reg-selected-title">

                <span>
                    รายชื่อผู้สมัครกิจกรรม
                </span>

                <h2>

                    <?= regE(
                        $selectedActivity[
                            'title'
                        ]
                    ) ?>

                </h2>

            </div>


        </div>


        <!-- =================================
             ข้อมูลกิจกรรม
        ================================== -->

        <div class="reg-info-grid">


            <div class="reg-info-card">

                <i class="fa-regular fa-calendar"></i>

                <div>

                    <span>
                        วันที่จัดกิจกรรม
                    </span>

                    <strong>

                        <?= regE(
                            regThaiDate(
                                $selectedActivity[
                                    'activity_date'
                                ]
                            )
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="reg-info-card">

                <i class="fa-regular fa-clock"></i>

                <div>

                    <span>
                        เวลา
                    </span>

                    <strong>

                        <?= regE(
                            regTime(
                                $selectedActivity[
                                    'start_time'
                                ],
                                $selectedActivity[
                                    'end_time'
                                ]
                            )
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="reg-info-card">

                <i class="fa-solid fa-location-dot"></i>

                <div>

                    <span>
                        สถานที่
                    </span>

                    <strong>

                        <?= regE(
                            $selectedActivity[
                                'location'
                            ] ?: '-'
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="reg-info-card">

                <i class="fa-solid fa-users"></i>

                <div>

                    <span>
                        จำนวนผู้สมัคร
                    </span>

                    <strong>

                        <?= $registeredCount ?>

                        <?php if (
                            !empty(
                                $selectedActivity[
                                    'max_participants'
                                ]
                            )
                        ): ?>

                            /
                            <?= (int)$selectedActivity[
                                'max_participants'
                            ] ?>

                        <?php endif; ?>

                        คน

                    </strong>

                </div>

            </div>


        </div>


        <!-- =================================
             สรุป
        ================================== -->

        <div class="reg-summary-grid">


            <div class="reg-summary-card active">

                <div class="reg-summary-icon">

                    <i class="fa-solid fa-user-check"></i>

                </div>

                <div>

                    <span>
                        ลงทะเบียนอยู่
                    </span>

                    <strong>
                        <?= $registeredCount ?>
                    </strong>

                    <small>
                        คน
                    </small>

                </div>

            </div>


            <div class="reg-summary-card cancelled">

                <div class="reg-summary-icon">

                    <i class="fa-solid fa-user-xmark"></i>

                </div>

                <div>

                    <span>
                        ยกเลิกแล้ว
                    </span>

                    <strong>
                        <?= $cancelledCount ?>
                    </strong>

                    <small>
                        คน
                    </small>

                </div>

            </div>


        </div>


        <!-- =================================
             Filter
        ================================== -->

        <form
            method="GET"
            class="reg-filter"
        >

            <input
                type="hidden"
                name="activity_id"
                value="<?= $activityId ?>"
            >


            <div class="reg-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="search"
                    name="search"
                    value="<?= regE($search) ?>"
                    placeholder="ค้นหาชื่อ รหัสนิสิต อีเมล หรือเบอร์โทร"
                >

            </div>


            <select name="status">

                <option
                    value=""
                    <?= $status === ''
                        ? 'selected'
                        : ''
                    ?>
                >
                    ทุกสถานะ
                </option>

                <option
                    value="registered"
                    <?= $status === 'registered'
                        ? 'selected'
                        : ''
                    ?>
                >
                    ลงทะเบียนแล้ว
                </option>

                <option
                    value="cancelled"
                    <?= $status === 'cancelled'
                        ? 'selected'
                        : ''
                    ?>
                >
                    ยกเลิกแล้ว
                </option>

            </select>


            <div
                class="reg-date-fields"
                id="registrationDateFields"
            >
                <label class="reg-date-filter" id="registrationDayField">
                    <span>วัน</span>
                    <select name="filter_day" id="registrationFilterDay">
                        <option value="">ทุกวัน</option>
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                            <option
                                value="<?= $day ?>"
                                data-day="<?= $day ?>"
                                <?= $day > $filterMaximumDay ? 'hidden disabled' : '' ?>
                                <?= $filterDay === $day ? 'selected' : '' ?>
                            >
                                <?= $day ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>

                <label class="reg-date-filter" id="registrationMonthField">
                    <span>เดือน</span>
                    <select name="filter_month" id="registrationFilterMonth">
                        <option value="">ทุกเดือน</option>
                        <?php foreach ($filterMonths as $monthNumber => $monthName): ?>
                            <option value="<?= $monthNumber ?>" <?= $filterMonth === $monthNumber ? 'selected' : '' ?>>
                                <?= regE($monthName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="reg-date-filter">
                    <span>ปี พ.ศ.</span>
                    <select name="filter_year" id="registrationFilterYear">
                        <option value="">ทุกปี</option>
                        <?php foreach ($registrationYears as $gregorianYear): ?>
                            <?php $buddhistYear = $gregorianYear + 543; ?>
                            <option value="<?= $buddhistYear ?>" <?= $filterYear === $buddhistYear ? 'selected' : '' ?>>
                                <?= $buddhistYear ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>


            <button
                type="submit"
                class="primary-btn"
            >

                <i class="fa-solid fa-filter"></i>

                กรอง

            </button>


            <a
                href="index.php?activity_id=<?= $activityId ?>"
                class="secondary-btn"
            >
                ล้างตัวกรอง
            </a>


        </form>


        <!-- =================================
             ตาราง
        ================================== -->

        <div class="reg-table-wrap">

            <table class="reg-table">

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
                            ข้อมูลการศึกษา
                        </th>

                        <th>
                            ติดต่อ
                        </th>

                        <th>
                            สถานะสมาชิก
                        </th>

                        <th>
                            สถานะการสมัคร
                        </th>

                        <th class="reg-cancel-reason-head">
                            สาเหตุที่ยกเลิก
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (
                        !empty($registrations)
                    ): ?>


                        <?php foreach (
                            $registrations
                            as $index => $registration
                        ): ?>


                            <tr>


                                <td>

                                    <?= $index + 1 ?>

                                </td>

                                <td>

                                    <strong class="reg-student-id">

                                        <?= regE(
                                            $registration[
                                                'student_id'
                                            ] ?: '-'
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <strong>

                                        <?= regE(
                                            trim(
                                                $registration[
                                                    'first_name'
                                                ] .
                                                ' ' .
                                                $registration[
                                                    'last_name'
                                                ]
                                            )
                                        ) ?>

                                    </strong>

                                    <small>

                                        <?= regE(
                                            $registration[
                                                'email'
                                            ]
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $registration[
                                                'major'
                                            ]
                                        )
                                    ): ?>

                                        <strong>

                                            <?= regE(
                                                $registration[
                                                    'major'
                                                ]
                                            ) ?>

                                        </strong>

                                        <small>

                                            <?= regE(
                                                $registration[
                                                    'faculty'
                                                ] ?: '-'
                                            ) ?>

                                        </small>

                                    <?php else: ?>

                                        <span class="reg-muted">
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <span>

                                        <i class="fa-regular fa-envelope"></i>

                                        <?= regE(
                                            $registration[
                                                'email'
                                            ]
                                        ) ?>

                                    </span>

                                    <small>

                                        <i class="fa-solid fa-phone"></i>

                                        <?= regE(
                                            $registration[
                                                'phone'
                                            ] ?: '-'
                                        ) ?>

                                    </small>

                                </td>


                                <td>


                                    <?php if (
                                        !empty(
                                            $registration[
                                                'member_status'
                                            ]
                                        )
                                    ): ?>


                                        <span class="reg-member-badge">

                                            <i class="fa-solid fa-id-card"></i>

                                            <?= regE(
                                                $registration[
                                                    'member_status'
                                                ]
                                            ) ?>

                                        </span>


                                    <?php else: ?>


                                        <span class="reg-muted">
                                            -
                                        </span>


                                    <?php endif; ?>


                                </td>


                                <td>


                                    <?php if (
                                        $registration[
                                            'registration_status'
                                        ] === 'registered'
                                    ): ?>


                                        <span class="reg-status registered">

                                            <i class="fa-solid fa-circle-check"></i>

                                            ลงทะเบียนแล้ว

                                        </span>

                                        <?php if (
                                            !empty(
                                                $registration[
                                                    'registered_at'
                                                ]
                                            )
                                        ): ?>

                                            <small class="reg-registered-date">
                                                สมัครเมื่อ
                                                <?= regE(
                                                    regThaiDate(
                                                        $registration[
                                                            'registered_at'
                                                        ]
                                                    )
                                                ) ?>
                                                <?= date(
                                                    'H:i',
                                                    strtotime(
                                                        $registration[
                                                            'registered_at'
                                                        ]
                                                    )
                                                ) ?>
                                                น.
                                            </small>

                                        <?php endif; ?>


                                    <?php elseif (
                                        $registration[
                                            'registration_status'
                                        ] === 'cancelled'
                                    ): ?>


                                        <span class="reg-status cancelled">

                                            <i class="fa-solid fa-circle-xmark"></i>

                                            ยกเลิกแล้ว

                                        </span>

                                        <?php if (
                                            !empty(
                                                $registration[
                                                    'registered_at'
                                                ]
                                            )
                                        ): ?>

                                            <small class="reg-registered-date">
                                                สมัครเมื่อ
                                                <?= regE(
                                                    regThaiDate(
                                                        $registration[
                                                            'registered_at'
                                                        ]
                                                    )
                                                ) ?>
                                                <?= date(
                                                    'H:i',
                                                    strtotime(
                                                        $registration[
                                                            'registered_at'
                                                        ]
                                                    )
                                                ) ?>
                                                น.
                                            </small>

                                        <?php endif; ?>


                                    <?php else: ?>


                                        <span class="reg-status other">

                                            <?= regE(
                                                $registration[
                                                    'registration_status'
                                                ]
                                            ) ?>

                                        </span>


                                    <?php endif; ?>


                                </td>


                                <td class="reg-cancel-reason-cell">

                                    <?php if (
                                        $registration[
                                            'registration_status'
                                        ] === 'cancelled'
                                    ): ?>

                                        <?php if (
                                            !empty(
                                                $registration[
                                                    'cancel_reason_text'
                                                ]
                                            )
                                        ): ?>

                                            <div class="reg-cancel-reason-text">
                                                <?= nl2br(
                                                    regE(
                                                        $registration[
                                                            'cancel_reason_text'
                                                        ]
                                                    )
                                                ) ?>
                                            </div>

                                            <?php if (
                                                !empty(
                                                    $registration[
                                                        'cancelled_datetime'
                                                    ]
                                                )
                                            ): ?>

                                                <small class="reg-cancel-date">
                                                    ยกเลิกเมื่อ
                                                    <?= date(
                                                        'd/m/Y H:i',
                                                        strtotime(
                                                            $registration[
                                                                'cancelled_datetime'
                                                            ]
                                                        )
                                                    ) ?>
                                                    น.
                                                </small>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            <span class="reg-muted">
                                                ไม่ระบุสาเหตุ
                                            </span>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="reg-muted">
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                class="reg-table-empty"
                            >

                                <i class="fa-solid fa-users-slash"></i>

                                <strong>
                                    <?= $registrationDateFilterActive
                                        ? 'ไม่มีผู้สมัครในช่วงเวลาที่เลือก'
                                        : 'ไม่พบผู้สมัคร'
                                    ?>
                                </strong>

                                <span>
                                    ยังไม่มีผู้สมัครตามเงื่อนไขที่เลือก
                                </span>

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>


        <div class="reg-table-result">

            แสดงทั้งหมด

            <strong>
                <?= count($registrations) ?>
            </strong>

            รายการ

        </div>


    <?php endif; ?>


</section>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const daySelect = document.getElementById('registrationFilterDay');
    const monthSelect = document.getElementById('registrationFilterMonth');
    const yearSelect = document.getElementById('registrationFilterYear');

    if (!daySelect || !monthSelect || !yearSelect) {
        return;
    }

    function updateAvailableDays() {
        const month = Number.parseInt(monthSelect.value, 10);
        const buddhistYear = Number.parseInt(yearSelect.value, 10);
        const gregorianYear = Number.isInteger(buddhistYear)
            ? buddhistYear - 543
            : 2000;
        const maximumDay = Number.isInteger(month)
            ? new Date(gregorianYear, month, 0).getDate()
            : 31;

        daySelect.querySelectorAll('option[data-day]').forEach(function (option) {
            const unavailable = Number.parseInt(option.dataset.day, 10) > maximumDay;
            option.hidden = unavailable;
            option.disabled = unavailable;
        });

        if (Number.parseInt(daySelect.value, 10) > maximumDay) {
            daySelect.value = '';
        }
    }

    monthSelect.addEventListener('change', updateAvailableDays);
    yearSelect.addEventListener('change', updateAvailableDays);
    updateAvailableDays();
});
</script>


<style>

/* ========================================
   REGISTRATIONS ADMIN
======================================== */

.reg-subtitle {
    margin-top: 4px;
    color: #8490a3;
    font-size: 12px;
}

.reg-section-head {
    margin: 12px 0 22px;
}

.reg-section-head h4 {
    margin: 0;
    color: #18345e;
    font-size: 18px;
}

.reg-section-head p {
    margin: 4px 0 0;
    color: #8490a3;
    font-size: 12px;
}


/* ========================================
   ACTIVITY GRID
======================================== */

.reg-activity-grid {
    display: grid;
    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );
    gap: 18px;
}

.reg-activity-card {
    padding: 20px;
    background: #ffffff;
    border: 1px solid #e1e7ef;
    border-radius: 15px;
}

.reg-card-top {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.reg-card-icon {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    color: #1a5bb5;
    background: #eaf2ff;
    border-radius: 11px;
    font-size: 18px;
}

.reg-publish-status {
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
}

.reg-publish-status.published {
    color: #17643d;
    background: #e5f7ed;
}

.reg-publish-status.draft {
    color: #8a6518;
    background: #fff2cf;
}

.reg-publish-status.hidden {
    color: #667085;
    background: #edf0f4;
}

.reg-activity-card h3 {
    min-height: 50px;
    margin: 0 0 15px;
    color: #18345e;
    font-size: 16px;
    line-height: 1.55;
}

.reg-card-info {
    display: grid;
    gap: 8px;
    margin-bottom: 18px;
}

.reg-card-info p {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #65758b;
    font-size: 11px;
}

.reg-card-info i {
    width: 16px;
    color: #2468c9;
    text-align: center;
}

.reg-count-box {
    padding: 13px;
    display: grid;
    grid-template-columns:
        1fr
        1px
        1fr;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border-radius: 10px;
}

.reg-count-box > div:not(
    .reg-count-divider
) {
    text-align: center;
}

.reg-count-box span {
    display: block;
    color: #8792a3;
    font-size: 10px;
}

.reg-count-box strong {
    display: block;
    margin-top: 2px;
    color: #18345e;
    font-size: 20px;
}

.reg-count-divider {
    width: 1px;
    height: 32px;
    background: #dfe5ed;
}

.reg-progress {
    height: 7px;
    margin-top: 13px;
    overflow: hidden;
    background: #edf1f5;
    border-radius: 999px;
}

.reg-progress-bar {
    height: 100%;
    background: #2568c6;
    border-radius: 999px;
}

.reg-capacity-text {
    margin-top: 5px;
    display: block;
    color: #8490a3;
    text-align: right;
    font-size: 10px;
}

.reg-card-actions {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.reg-view-btn,
.reg-report-btn {
    min-height: 42px;
    padding: 9px 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    color: #ffffff;
    background: #18345e;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.reg-report-btn {
    color: #18345e;
    background: #eaf2ff;
    border: 1px solid #cbdcf5;
}

.reg-view-btn:hover {
    background: #102a4e;
}

.reg-report-btn:hover {
    background: #dceaff;
}


/* ========================================
   SELECTED ACTIVITY
======================================== */

.reg-selected-head {
    margin-bottom: 22px;
}

.reg-selected-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.reg-selected-title {
    margin-top: 22px;
}

.reg-selected-title span {
    color: #78869a;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .05em;
}

.reg-selected-title h2 {
    margin: 5px 0 0;
    color: #18345e;
    font-size: 23px;
}


/* ========================================
   INFO
======================================== */

.reg-info-grid {
    margin-bottom: 20px;
    display: grid;
    grid-template-columns:
        repeat(
            4,
            minmax(0, 1fr)
        );
    gap: 12px;
}

.reg-info-card {
    min-height: 92px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 13px;
    background: #f8fafc;
    border: 1px solid #e4e9f0;
    border-radius: 12px;
}

.reg-info-card > i {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex: 0 0 40px;
    color: #2468c9;
    background: #e9f2ff;
    border-radius: 10px;
}

.reg-info-card span {
    display: block;
    color: #8792a3;
    font-size: 10px;
}

.reg-info-card strong {
    margin-top: 3px;
    display: block;
    color: #253956;
    font-size: 12px;
}


/* ========================================
   SUMMARY
======================================== */

.reg-summary-grid {
    margin-bottom: 22px;
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0, 220px)
        );
    gap: 12px;
}

.reg-summary-card {
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 13px;
    border-radius: 12px;
}

.reg-summary-card.active {
    color: #17643d;
    background: #edf9f2;
    border: 1px solid #c5ead4;
}

.reg-summary-card.cancelled {
    color: #9b3540;
    background: #fff2f3;
    border: 1px solid #f3c7cc;
}

.reg-summary-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: rgba(
        255,
        255,
        255,
        .7
    );
    border-radius: 10px;
}

.reg-summary-card span,
.reg-summary-card strong,
.reg-summary-card small {
    display: inline-block;
}

.reg-summary-card span {
    width: 100%;
    font-size: 10px;
}

.reg-summary-card strong {
    margin-top: 1px;
    font-size: 22px;
}

.reg-summary-card small {
    margin-left: 3px;
    font-size: 10px;
}


/* ========================================
   FILTER
======================================== */

.reg-filter {
    margin-bottom: 18px;
    display: grid;
    grid-template-columns:
        minmax(170px, 1fr)
        126px
        minmax(284px, auto)
        max-content
        max-content;
    align-items: end;
    gap: 10px;
}

.reg-search {
    position: relative;
    min-width: 0;
}

.reg-search i {
    position: absolute;
    top: 50%;
    left: 14px;
    color: #9099a8;
    transform: translateY(-50%);
}

.reg-search input,
.reg-filter select {
    height: 43px;
    min-height: 43px;
    border: 1px solid #d9e0e9;
    border-radius: 9px;
    background: #ffffff;
    font-family: "Prompt", sans-serif;
    outline: none;
}

.reg-search input {
    width: 100%;
    padding: 9px 13px 9px 40px;
}

.reg-filter select {
    min-width: 165px;
    padding: 9px 12px;
}

.reg-filter > select {
    width: 100%;
    min-width: 0;
}

.reg-date-fields {
    min-width: 0;
    display: grid;
    grid-template-columns: 82px 110px 92px;
    align-items: end;
    gap: 7px;
}

.reg-date-filter {
    min-width: 0;
    display: grid;
    grid-template-rows: 15px 43px;
    gap: 4px;
    color: #66758b;
    font-size: 10px;
}

.reg-date-filter span {
    line-height: 15px;
    white-space: nowrap;
}

.reg-date-filter input,
.reg-date-filter select {
    width: 100%;
    min-width: 0;
    height: 43px;
    min-height: 43px;
    padding: 9px 12px;
    border: 1px solid #d9e0e9;
    border-radius: 9px;
    background: #ffffff;
    font-family: "Prompt", sans-serif;
}

.reg-filter > .primary-btn,
.reg-filter > .secondary-btn {
    min-height: 43px;
    height: 43px;
    align-self: end;
    white-space: nowrap;
}


/* ========================================
   TABLE
======================================== */

.reg-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e1e6ed;
    border-radius: 12px;
}

.reg-table {
    width: 100%;
    min-width: 1190px;
    border-collapse: collapse;
    background: #ffffff;
    font-size: 11px;
}

.reg-table th {
    padding: 13px;
    color: #6e7c90;
    background: #f6f8fb;
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
}

.reg-table td {
    padding: 14px 13px;
    color: #34445c;
    border-top: 1px solid #edf0f4;
    vertical-align: middle;
}

.reg-table td strong,
.reg-table td small {
    display: block;
}

.reg-table td small {
    margin-top: 4px;
    color: #8590a1;
    font-size: 9px;
}

.reg-student-id {
    color: #18345e;
}

.reg-status,
.reg-member-badge {
    padding: 5px 9px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 600;
    white-space: nowrap;
}

.reg-status.registered {
    color: #17643d;
    background: #e3f7ec;
}

.reg-status.cancelled {
    color: #a3343e;
    background: #ffe9eb;
}

.reg-status.other {
    color: #5e697a;
    background: #edf0f4;
}

.reg-registered-date {
    display: block !important;
    margin-top: 6px !important;
    color: #8b96a8 !important;
    font-size: 8px !important;
    line-height: 1.45;
    white-space: nowrap;
}

.reg-member-badge {
    color: #315a94;
    background: #edf3fb;
}

.reg-muted {
    color: #9aa3b1;
}


/* ========================================
   CANCELLATION REASON
======================================== */

.reg-cancel-reason-head {
    min-width: 190px;
}

.reg-cancel-reason-cell {
    min-width: 190px;
    max-width: 260px;
    white-space: normal;
}

.reg-cancel-reason-text {
    color: #46566d;
    font-size: 10px;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.reg-cancel-date {
    margin-top: 5px !important;
    color: #9aa3b1 !important;
    font-size: 8px !important;
    line-height: 1.4;
}

.reg-table-empty {
    padding: 55px 20px !important;
    color: #8a95a5 !important;
    text-align: center;
}

.reg-table-empty i {
    margin-bottom: 9px;
    display: block;
    font-size: 25px;
}

.reg-table-empty strong,
.reg-table-empty span {
    display: block;
}

.reg-table-result {
    padding-top: 12px;
    color: #8791a1;
    font-size: 11px;
    text-align: right;
}

.reg-table-result strong {
    color: #18345e;
}


/* ========================================
   EMPTY
======================================== */

.reg-empty {
    padding: 65px 20px;
    color: #7f8a9c;
    text-align: center;
    border: 1px dashed #d8dee7;
    border-radius: 14px;
}

.reg-empty i {
    margin-bottom: 12px;
    color: #b0b8c4;
    font-size: 30px;
}

.reg-empty h3 {
    margin: 0 0 5px;
    color: #40516c;
}

.reg-empty p {
    margin: 0;
    font-size: 12px;
}


/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 1100px) {

    .reg-activity-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }

    .reg-info-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }

    .reg-filter {
        grid-template-columns:
            minmax(0, 1fr)
            140px
            max-content
            max-content;
    }

    .reg-search {
        grid-column: 1;
        grid-row: 1;
    }

    .reg-filter > select {
        grid-column: 2;
        grid-row: 1;
    }

    .reg-date-fields {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .reg-filter > .primary-btn {
        grid-column: 3;
        grid-row: 1;
    }

    .reg-filter > .secondary-btn {
        grid-column: 4;
        grid-row: 1;
    }

}

@media (max-width: 700px) {

    .reg-activity-grid,
    .reg-info-grid,
    .reg-summary-grid {
        grid-template-columns: 1fr;
    }

    .reg-filter {
        grid-template-columns:
            minmax(0, 1fr)
            minmax(125px, .45fr);
    }

    .reg-search,
    .reg-filter > select,
    .reg-date-fields,
    .reg-filter > .primary-btn,
    .reg-filter > .secondary-btn {
        grid-row: auto;
    }

    .reg-search {
        grid-column: 1;
    }

    .reg-filter > select {
        grid-column: 2;
    }

    .reg-date-fields {
        grid-column: 1 / -1;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .reg-filter > .primary-btn {
        grid-column: 1;
    }

    .reg-filter > .secondary-btn {
        grid-column: 2;
    }

    .reg-filter select,
    .reg-date-fields,
    .reg-date-filter,
    .reg-date-filter input,
    .reg-filter > .primary-btn,
    .reg-filter > .secondary-btn {
        width: 100%;
    }

}

@media (max-width: 420px) {

    .reg-filter {
        grid-template-columns: 1fr;
    }

    .reg-search,
    .reg-filter > select,
    .reg-date-fields,
    .reg-filter > .primary-btn,
    .reg-filter > .secondary-btn {
        grid-column: 1;
    }

    .reg-card-actions {
        grid-template-columns: 1fr;
    }

}

/* Dedicated roster document; screen layout remains unchanged. */
.registration-print-sheet {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    html,
    body {
        width: 100%;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
    }

    body * {
        visibility: hidden !important;
    }

    .registration-print-sheet,
    .registration-print-sheet * {
        visibility: visible !important;
    }

    .registration-print-sheet {
        display: block !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
        font-family: inherit;
        font-size: 10pt;
    }

    section.panel.full-panel {
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    section.panel.full-panel > :not(.registration-print-sheet) {
        display: none !important;
    }

    .registration-print-header {
        display: flex;
        align-items: center;
        gap: 7mm;
        min-height: 28mm;
        margin-bottom: 4mm;
        padding-bottom: 3mm;
        border-bottom: .45mm solid #173e70;
    }

    .registration-print-logo {
        display: block;
        flex: 0 0 23mm;
        width: 23mm;
        height: 23mm;
        object-fit: contain;
    }

    .registration-print-heading {
        min-width: 0;
        color: #17365f !important;
        font-size: 12pt;
        font-weight: 400;
        line-height: 1.35;
    }

    .registration-print-heading p {
        margin: 0;
        color: #17365f !important;
        font-weight: 400;
    }

    .registration-print-heading .registration-print-club-name {
        font-size: 17pt;
        font-weight: 500;
    }

    .registration-print-title {
        margin: 3mm 0 5mm;
        color: #17365f !important;
        font-size: 17pt;
        font-weight: 600;
        text-align: center;
    }

    .registration-print-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2mm;
        margin: 0 0 5mm;
        padding: 3.5mm 4mm;
        border: .25mm solid #d5e3f0;
        border-radius: 2mm;
        background: #f2f7fc !important;
        font-size: 11pt;
    }

    .registration-print-meta p {
        display: grid;
        grid-template-columns: 38mm minmax(0, 1fr);
        gap: 2mm;
        margin: 0;
        color: #183d68 !important;
        line-height: 1.4;
    }

    .registration-print-meta strong {
        font-weight: 600;
    }

    .registration-print-meta span {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .registration-print-table {
        width: 100%;
        max-width: none;
        border-collapse: collapse;
        table-layout: fixed;
        color: #000 !important;
        font-size: 10pt;
    }

    .registration-print-table th,
    .registration-print-table td {
        border: 1px solid #222 !important;
        padding: 1.2mm 1mm;
        color: #000 !important;
        text-align: left;
        vertical-align: middle;
        overflow-wrap: anywhere;
        word-break: normal;
        line-height: 1.35;
    }

    .registration-print-table th {
        background-color: #173e70 !important;
        color: #fff !important;
        font-weight: 700;
        font-size: 11pt;
        text-align: center;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .registration-print-table tbody tr:nth-child(even) td {
        background-color: #f1f3f6 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .registration-print-table tbody tr:nth-child(odd) td {
        background-color: #fff !important;
    }

    @supports not (print-color-adjust: exact) {
        .registration-print-table th {
            background-color: #e5ebf3 !important;
            color: #17365f !important;
        }
    }

    .registration-print-table th:nth-child(1) { width: 6%; }
    .registration-print-table th:nth-child(2) { width: 22%; }
    .registration-print-table th:nth-child(3) { width: 14%; }
    .registration-print-table th:nth-child(4) { width: 22%; }
    .registration-print-table th:nth-child(5) { width: 15%; }
    .registration-print-table th:nth-child(6) { width: 21%; }

    .registration-print-table th:nth-child(4),
    .registration-print-table td:nth-child(4) {
        white-space: nowrap;
        overflow-wrap: normal;
        word-break: keep-all;
    }

    .registration-print-table thead {
        display: table-header-group;
    }

    .registration-print-table tr {
        height: 10.5mm;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .registration-print-table .registration-print-number {
        text-align: center;
    }

    .registration-print-table .registration-signature {
        min-height: 10.5mm;
    }

    .registration-print-signoff {
        margin: 5mm 0 0;
        break-inside: avoid;
        page-break-inside: avoid;
        color: #000 !important;
        font-size: 10.5pt;
    }

    .registration-print-total {
        display: flex;
        justify-content: space-between;
        gap: 7mm;
        width: fit-content;
        min-width: 78mm;
        margin: 0 0 7mm auto;
        padding: 2.5mm 4mm;
        border-radius: 2mm;
        background: #edf5fc !important;
        color: #17365f !important;
        font-weight: 600;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .registration-print-signoff-fields {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 6mm;
    }

    .registration-print-signoff-fields p {
        display: flex;
        align-items: flex-end;
        gap: 2mm;
        margin: 0;
        white-space: nowrap;
    }

    .registration-print-signoff-paren {
        justify-content: flex-end;
        margin-top: 2mm !important;
    }

    .registration-print-date {
        margin-top: 2mm !important;
    }

    .registration-print-signoff-line {
        display: inline-block;
        width: 45mm;
        height: 5mm;
        border-bottom: 1px solid #222;
    }

    .registration-print-name-line {
        display: inline-block;
        width: 55mm;
        height: 5mm;
        border-bottom: 1px solid #222;
    }

    .registration-print-date-line {
        display: inline-block;
        width: 11mm;
        height: 5mm;
        border-bottom: 1px solid #222;
    }

    .registration-print-date-line.registration-print-year-line {
        width: 17mm;
    }

    .registration-print-empty {
        padding: 8mm 2mm !important;
        text-align: center !important;
    }
}

</style>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
