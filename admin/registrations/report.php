<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// report.php
// หน้านี้ใช้สำหรับแสดงและพิมพ์รายงานผู้สมัครของกิจกรรมแต่ละกิจกรรม

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'รายงานกิจกรรม';


// =========================================
// ฟังก์ชันช่วยแสดงผล
// =========================================

function activityReportE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function activityReportTime(
    ?string $start,
    ?string $end
): string {

    if (empty($start)) {
        return '-';
    }

    $text = date('H:i', strtotime($start));

    if (!empty($end)) {
        $text .= ' - ' . date('H:i', strtotime($end));
    }

    return $text . ' น.';
}


function activityReportDateTime(?string $dateTime): string
{
    if (empty($dateTime)) {
        return '-';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return '-';
    }

    return
        date('d/m/', $timestamp)
        . ((int)date('Y', $timestamp) + 543)
        . ' '
        . date('H:i', $timestamp)
        . ' น.';
}


// =========================================
// ตรวจสอบ activity_id
// =========================================

$rawActivityId = $_GET['activity_id'] ?? null;

$validatedActivityId = filter_var(
    $rawActivityId,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

$activityId =
    $validatedActivityId !== false
    ? (int)$validatedActivityId
    : 0;

$activity = null;
$registrations = [];

$summary = [
    'total' => 0,
    'registered' => 0,
    'cancelled' => 0
];


// =========================================
// ดึงข้อมูลกิจกรรมด้วย Prepared Statement
// =========================================

if ($activityId > 0) {

    $activityStmt = $pdo->prepare("
        SELECT
            id,
            title,
            activity_date,
            start_time,
            end_time,
            location,
            max_participants

        FROM activities

        WHERE id = ?

        LIMIT 1
    ");

    $activityStmt->execute([
        $activityId
    ]);

    $activity = $activityStmt->fetch(
        PDO::FETCH_ASSOC
    );
}


// =========================================
// ดึงสรุปและรายชื่อของกิจกรรมที่พบ
// =========================================

if ($activity) {

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COUNT(
                CASE
                    WHEN registration_status = 'registered'
                    THEN 1
                END
            ) AS registered,
            COUNT(
                CASE
                    WHEN registration_status = 'cancelled'
                    THEN 1
                END
            ) AS cancelled

        FROM activity_registrations

        WHERE activity_id = ?
    ");

    $summaryStmt->execute([
        $activityId
    ]);

    $summaryRow = $summaryStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($summaryRow) {
        $summary['total'] = (int)$summaryRow['total'];
        $summary['registered'] = (int)$summaryRow['registered'];
        $summary['cancelled'] = (int)$summaryRow['cancelled'];
    }


    $registrationStmt = $pdo->prepare("
        SELECT
            ar.registration_status,
            ar.cancel_reason,
            ar.registered_at,
            ar.cancelled_at,

            u.first_name,
            u.last_name,

            m.student_id,
            m.admission_year,
            m.faculty,
            m.major,
            m.year_level,
            m.member_status

        FROM activity_registrations ar

        INNER JOIN users u
            ON u.id = ar.user_id

        LEFT JOIN members m
            ON m.user_id = u.id

        WHERE ar.activity_id = ?

        ORDER BY
            CASE
                WHEN ar.registration_status = 'registered'
                THEN 1
                WHEN ar.registration_status = 'cancelled'
                THEN 2
                ELSE 3
            END,
            u.first_name ASC,
            u.last_name ASC
    ");

    $registrationStmt->execute([
        $activityId
    ]);

    $registrations = $registrationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($registrations as &$registration) {

        if (!empty($registration['student_id'])) {
            $memberStatus = getAutomaticMemberStatus($registration);
            $registration['display_year_level'] =
                $memberStatus['year_level'];
        } else {
            $registration['display_year_level'] = null;
        }
    }

    unset($registration);
}


$capacity =
    $activity && $activity['max_participants'] !== null
    ? (int)$activity['max_participants']
    : null;

$remainingSeats =
    $capacity !== null
    ? max(0, $capacity - $summary['registered'])
    : null;


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>


<section class="panel full-panel activity-report-page">

    <?php if (!$activity): ?>

        <div class="activity-report-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <h2>
                <?= $activityId <= 0
                    ? 'ไม่ได้ระบุกิจกรรมที่ต้องการดูรายงาน'
                    : 'ไม่พบกิจกรรมที่ต้องการดูรายงาน'
                ?>
            </h2>

            <p>
                กรุณากลับไปเลือกกิจกรรมจากหน้าผู้สมัครกิจกรรมอีกครั้ง
            </p>

            <a href="index.php" class="secondary-btn">
                <i class="fa-solid fa-arrow-left"></i>
                ย้อนกลับ
            </a>

        </div>

    <?php else: ?>

        <div class="activity-report-control no-print">

            <div>

                <small>
                    ACTIVITY REPORT
                </small>

                <h2>
                    รายงานกิจกรรม
                </h2>

                <p>
                    ชื่อกิจกรรม:
                    <strong><?= activityReportE($activity['title']) ?></strong>
                </p>

            </div>


            <div class="activity-report-actions">

                <a href="index.php" class="secondary-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    ย้อนกลับ
                </a>

                <button
                    type="button"
                    class="primary-btn"
                    onclick="window.print()"
                >
                    <i class="fa-solid fa-print"></i>
                    พิมพ์รายงาน
                </button>

            </div>

        </div>


        <article class="activity-report-document">

            <header class="activity-report-header">

                <img
                    src="<?= BC_BASE_PATH ?>/assets/images/logo.png"
                    alt="Bizcomp Club"
                >

                <h1>รายงานกิจกรรม</h1>

                <p>ชมรมบิตคอม คณะการบัญชีและการจัดการ</p>
                <p>มหาวิทยาลัยมหาสารคาม</p>

                <div class="activity-report-name">
                    <span>ชื่อกิจกรรม :</span>
                    <strong><?= activityReportE($activity['title']) ?></strong>
                </div>

            </header>


            <section class="activity-report-section">

                <h2>ข้อมูลกิจกรรม</h2>

                <table class="activity-report-detail-table">
                    <tbody>
                        <tr>
                            <th>ชื่อกิจกรรม</th>
                            <td><?= activityReportE($activity['title']) ?></td>
                        </tr>
                        <tr>
                            <th>วันที่จัดกิจกรรม</th>
                            <td><?= activityReportE(formatThaiDate($activity['activity_date'])) ?></td>
                        </tr>
                        <tr>
                            <th>เวลา</th>
                            <td><?= activityReportE(activityReportTime($activity['start_time'], $activity['end_time'])) ?></td>
                        </tr>
                        <tr>
                            <th>สถานที่</th>
                            <td><?= activityReportE($activity['location'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <th>จำนวนที่รับ</th>
                            <td>
                                <?= $capacity !== null
                                    ? number_format($capacity) . ' คน'
                                    : 'ไม่จำกัดจำนวน'
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </section>


            <section class="activity-report-section activity-report-summary">

                <h2>สรุปข้อมูลการลงทะเบียน</h2>

                <p>
                    <?php if ($capacity !== null): ?>
                        กิจกรรมนี้เปิดรับผู้เข้าร่วมจำนวน
                        <strong><?= number_format($capacity) ?> คน</strong>
                    <?php else: ?>
                        กิจกรรมนี้เปิดรับผู้เข้าร่วมโดยไม่จำกัดจำนวน
                    <?php endif; ?>

                    <?php if ($summary['total'] === 0): ?>
                        ปัจจุบันยังไม่มีผู้สมัครเข้าร่วมกิจกรรม

                        <?php if ($remainingSeats !== null): ?>
                            และมีที่นั่งคงเหลือจำนวน
                            <strong><?= number_format($remainingSeats) ?> ที่นั่ง</strong>
                        <?php endif; ?>
                    <?php else: ?>
                        ปัจจุบันมีผู้สมัครทั้งหมด
                        <strong><?= number_format($summary['total']) ?> คน</strong>
                        โดยมีผู้ที่ยังคงลงทะเบียนเข้าร่วมกิจกรรมจำนวน
                        <strong><?= number_format($summary['registered']) ?> คน</strong>
                        ยกเลิกการลงทะเบียนจำนวน
                        <strong><?= number_format($summary['cancelled']) ?> คน</strong>

                        <?php if ($remainingSeats !== null): ?>
                            และมีที่นั่งคงเหลือจำนวน
                            <strong><?= number_format($remainingSeats) ?> ที่นั่ง</strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

            </section>


            <section class="activity-report-section activity-report-list-section">

                <h2>รายชื่อผู้สมัครกิจกรรม</h2>


                <div class="activity-report-table-wrap">

                    <table class="activity-report-table">

                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>รหัสนิสิต</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>คณะ</th>
                            <th>สาขา</th>
                            <th>ชั้นปี</th>
                            <th>วันที่ลงทะเบียน</th>
                            <th>สถานะ</th>
                            <th>สาเหตุที่ยกเลิก</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($registrations): ?>

                            <?php foreach ($registrations as $index => $registration): ?>

                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= activityReportE($registration['student_id'] ?: '-') ?></td>
                                    <td><?= activityReportE(trim($registration['first_name'] . ' ' . $registration['last_name'])) ?></td>
                                    <td><?= activityReportE($registration['faculty'] ?: '-') ?></td>
                                    <td><?= activityReportE($registration['major'] ?: '-') ?></td>
                                    <td>
                                        <?= $registration['display_year_level'] !== null
                                            ? (int)$registration['display_year_level']
                                            : '-'
                                        ?>
                                    </td>
                                    <td><?= activityReportE(activityReportDateTime($registration['registered_at'])) ?></td>
                                    <td>

                                        <?php if ($registration['registration_status'] === 'registered'): ?>
                                            <span class="activity-report-status registered">
                                                ลงทะเบียน
                                            </span>
                                        <?php elseif ($registration['registration_status'] === 'cancelled'): ?>
                                            <span class="activity-report-status cancelled">
                                                ยกเลิก
                                            </span>
                                        <?php else: ?>
                                            <span class="activity-report-status">
                                                <?= activityReportE($registration['registration_status']) ?>
                                            </span>
                                        <?php endif; ?>

                                    </td>
                                    <td>
                                        <?= $registration['registration_status'] === 'cancelled'
                                            ? activityReportE($registration['cancel_reason'] ?: 'ไม่ระบุสาเหตุ')
                                            : '-'
                                        ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="activity-report-empty">
                                    ยังไม่มีผู้สมัครเข้าร่วมกิจกรรมนี้
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                    </table>

                </div>

            </section>

        </article>

    <?php endif; ?>

</section>


<style>

.activity-report-control {
    margin-bottom: 22px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.activity-report-control small {
    color: #3e75bf;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .08em;
}

.activity-report-control h2 {
    margin: 3px 0 4px;
    color: #18345e;
    font-size: 22px;
}

.activity-report-control p {
    margin: 0;
    color: #6d7c91;
    font-size: 12px;
}

.activity-report-control p strong {
    color: #314967;
}

.activity-report-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.activity-report-document {
    max-width: 1180px;
    margin: 0 auto;
    padding: 42px 48px;
    background: #ffffff;
    border: 1px solid #dce2e9;
    border-radius: 3px;
    box-shadow: 0 8px 24px rgba(24, 52, 94, .06);
}

.activity-report-header {
    padding-bottom: 24px;
    text-align: center;
    border-bottom: 1.5px solid #18345e;
}

.activity-report-header img {
    width: 74px;
    height: 74px;
    margin: 0 auto 10px;
    display: block;
    object-fit: contain;
}

.activity-report-header h1 {
    margin: 0 0 6px;
    color: #18345e;
    font-size: 24px;
    font-weight: 600;
}

.activity-report-header p {
    margin: 1px 0;
    color: #3f4e63;
    font-size: 12px;
}

.activity-report-name {
    margin-top: 16px;
    color: #26384f;
    font-size: 13px;
}

.activity-report-name span {
    margin-right: 5px;
}

.activity-report-name strong {
    color: #18345e;
    font-weight: 600;
}

.activity-report-section {
    padding: 22px 0;
    border-bottom: 1px solid #d8dee7;
}

.activity-report-section h2 {
    margin: 0 0 13px;
    color: #18345e;
    font-size: 15px;
    font-weight: 600;
}

.activity-report-detail-table {
    width: 100%;
    border-collapse: collapse;
    color: #303d4f;
    font-size: 11px;
}

.activity-report-detail-table th,
.activity-report-detail-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #edf0f4;
    text-align: left;
    vertical-align: top;
}

.activity-report-detail-table tr:last-child th,
.activity-report-detail-table tr:last-child td {
    border-bottom: 0;
}

.activity-report-detail-table th {
    width: 185px;
    color: #3d4d64;
    background: #f7f8fa;
    font-weight: 500;
}

.activity-report-detail-table th::after {
    content: " :";
}

.activity-report-summary p {
    max-width: 960px;
    margin: 0;
    color: #34445a;
    font-size: 11px;
    line-height: 2;
}

.activity-report-summary p strong {
    color: #172f52;
    font-weight: 600;
}

.activity-report-list-section {
    padding-bottom: 0;
    border-bottom: 0;
}

.activity-report-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.activity-report-table {
    width: 100%;
    min-width: 960px;
    border-collapse: collapse;
    font-size: 10px;
}

.activity-report-table th {
    padding: 9px 7px;
    color: #2d3e56;
    background: #eef2f6;
    border: 1px solid #cfd7e2;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;
}

.activity-report-table td {
    padding: 9px 7px;
    color: #344155;
    border: 1px solid #d9dfe7;
    vertical-align: top;
}

.activity-report-table th:first-child,
.activity-report-table td:first-child,
.activity-report-table th:nth-child(6),
.activity-report-table td:nth-child(6),
.activity-report-table th:nth-child(8),
.activity-report-table td:nth-child(8) {
    text-align: center;
}

.activity-report-status {
    color: #465469;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.activity-report-status.registered {
    color: #34584a;
}

.activity-report-status.cancelled {
    color: #7b4b50;
}

.activity-report-empty {
    padding: 35px !important;
    color: #8792a3 !important;
    text-align: center !important;
}

.activity-report-error {
    padding: 65px 20px;
    color: #78869a;
    text-align: center;
}

.activity-report-error > i {
    color: #d45a64;
    font-size: 35px;
}

.activity-report-error h2 {
    margin: 13px 0 5px;
    color: #263d5e;
    font-size: 20px;
}

.activity-report-error p {
    margin: 0 0 18px;
    font-size: 12px;
}

@media (max-width: 1500px) {

    .activity-report-document {
        padding-right: 32px;
        padding-left: 32px;
    }

}

@media (max-width: 700px) {

    .activity-report-control {
        flex-direction: column;
    }

    .activity-report-actions,
    .activity-report-actions .primary-btn,
    .activity-report-actions .secondary-btn {
        width: 100%;
    }

    .activity-report-document {
        padding: 26px 18px;
    }

    .activity-report-header img {
        width: 62px;
        height: 62px;
    }

    .activity-report-header h1 {
        font-size: 21px;
    }

    .activity-report-name span,
    .activity-report-name strong {
        display: block;
    }

    .activity-report-name span {
        margin: 0 0 3px;
    }

    .activity-report-detail-table th {
        width: 125px;
    }

}

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

    .panel.full-panel.activity-report-page {
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .activity-report-document {
        max-width: none;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .activity-report-header {
        padding-bottom: 10px;
        border-color: #000000;
    }

    .activity-report-header img {
        width: 50px;
        height: 50px;
        margin-bottom: 4px;
    }

    .activity-report-header h1 {
        margin-bottom: 2px;
        color: #000000;
        font-size: 17px;
    }

    .activity-report-header p {
        margin: 1px 0;
        color: #000000;
        font-size: 8px;
    }

    .activity-report-name {
        margin-top: 6px;
        color: #000000;
        font-size: 8px;
    }

    .activity-report-name strong {
        color: #000000;
    }

    .activity-report-section {
        padding: 9px 0;
        border-color: #777777;
        break-inside: avoid;
    }

    .activity-report-section h2 {
        margin-bottom: 5px;
        color: #000000;
        font-size: 9px;
    }

    .activity-report-detail-table {
        color: #000000;
        font-size: 8px;
    }

    .activity-report-detail-table th,
    .activity-report-detail-table td {
        padding: 3px 5px;
        border-color: #aaaaaa;
    }

    .activity-report-detail-table th {
        width: 120px;
        color: #000000;
        background: #ffffff !important;
    }

    .activity-report-summary p,
    .activity-report-summary p strong {
        max-width: none;
        color: #000000;
        font-size: 8px;
        line-height: 1.7;
    }

    .activity-report-list-section {
        break-inside: auto;
    }

    .activity-report-table-wrap {
        overflow: visible;
        border: 0;
        border-radius: 0;
    }

    .activity-report-table {
        min-width: 0;
        table-layout: fixed;
        font-size: 8px;
    }

    .activity-report-table th,
    .activity-report-table td {
        padding: 4px 3px;
        color: #000000;
        border: 1px solid #555555;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .activity-report-table th {
        background: #f0f0f0 !important;
        text-align: center;
        white-space: normal;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .activity-report-table th:nth-child(1) { width: 4%; }
    .activity-report-table th:nth-child(2) { width: 10%; }
    .activity-report-table th:nth-child(3) { width: 17%; }
    .activity-report-table th:nth-child(4) { width: 14%; }
    .activity-report-table th:nth-child(5) { width: 13%; }
    .activity-report-table th:nth-child(6) { width: 6%; }
    .activity-report-table th:nth-child(7) { width: 12%; }
    .activity-report-table th:nth-child(8) { width: 9%; }
    .activity-report-table th:nth-child(9) { width: 15%; }

    .activity-report-table tr {
        break-inside: avoid;
    }

    .activity-report-table thead {
        display: table-header-group;
    }

    .activity-report-status,
    .activity-report-status.registered,
    .activity-report-status.cancelled {
        color: #000000;
        font-size: 8px;
        font-weight: 400;
        white-space: normal;
    }

}

</style>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
