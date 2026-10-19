<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// members.php
// หน้านี้ใช้สำหรับแสดงรายงานสรุปสมาชิกและพิมพ์รายงานสมาชิกของ Bizcomp Club

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminPageTitle = 'สรุปสมาชิก / รายงาน';

$yearFilter = $_GET['year'] ?? '';
$statusFilter = $_GET['status'] ?? 'current';


/* =========================================
   ดึงสมาชิกทั้งหมด
========================================= */

$stmt = $pdo->query("
    SELECT
        m.*,
        u.first_name,
        u.last_name,
        u.email
    FROM members m
    INNER JOIN users u
        ON u.id = m.user_id
    ORDER BY m.student_id ASC
");

$allMembers = $stmt->fetchAll();

$reportMembers = [];

$summary = [
    'current' => 0,
    'year1' => 0,
    'year2' => 0,
    'year3' => 0,
    'year4' => 0,
    'graduated' => 0,
    'inactive' => 0,
    'suspended' => 0
];


/* =========================================
   คำนวณชั้นปีและสถานะ
========================================= */

foreach ($allMembers as $member) {

    $status = getAutomaticMemberStatus($member);
    $admissionYear = getMemberAdmissionYear($member);

    $member['calculated_year'] = $status['year_level'];
    $member['calculated_status'] = $status['code'];
    $member['calculated_status_text'] = $status['text'];
    $member['calculated_admission_year'] = $admissionYear;


    /* =====================================
       สรุปจำนวนสมาชิกทั้งหมด
    ====================================== */

    if ($status['code'] === 'active') {

        $summary['current']++;

        if ($status['year_level'] === 1) {
            $summary['year1']++;
        }

        if ($status['year_level'] === 2) {
            $summary['year2']++;
        }

        if ($status['year_level'] === 3) {
            $summary['year3']++;
        }

        if ($status['year_level'] === 4) {
            $summary['year4']++;
        }
    }


    if ($status['code'] === 'graduated') {
        $summary['graduated']++;
    }

    if ($status['code'] === 'inactive') {
        $summary['inactive']++;
    }

    if ($status['code'] === 'suspended') {
        $summary['suspended']++;
    }


    /* =====================================
       กรองสถานะ
    ====================================== */

    if (
        $statusFilter === 'current'
        && $status['code'] !== 'active'
    ) {
        continue;
    }

    if (
        $statusFilter === 'graduated'
        && $status['code'] !== 'graduated'
    ) {
        continue;
    }

    if (
        $statusFilter === 'inactive'
        && $status['code'] !== 'inactive'
    ) {
        continue;
    }

    if (
        $statusFilter === 'suspended'
        && $status['code'] !== 'suspended'
    ) {
        continue;
    }


    /* =====================================
       กรองชั้นปี
    ====================================== */

    if ($yearFilter !== '') {

        if (
            (string)$status['year_level']
            !== (string)$yearFilter
        ) {
            continue;
        }
    }


    $reportMembers[] = $member;
}


/* =========================================
   ข้อมูลรายงาน
========================================= */

$currentAcademicYear = getCurrentAcademicYear();

$reportDate = formatThaiDate(date('Y-m-d'));

$reportTotal = count($reportMembers);


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>


<!-- =========================================
     ส่วนควบคุมรายงาน
========================================= -->

<section class="report-control no-print">

    <div>

        <small>
            MEMBER REPORT
        </small>

        <h2>
            สรุปข้อมูลสมาชิกชมรม
        </h2>

        <p>
            ปีการศึกษา <?= (int)$currentAcademicYear ?>
            · ข้อมูล ณ วันที่ <?= htmlspecialchars($reportDate) ?>
        </p>

    </div>


    <div class="report-actions">

        <a
            href="../members/index.php"
            class="secondary-btn"
        >
            <i class="fa-solid fa-arrow-left"></i>
            กลับหน้าสมาชิก
        </a>


        <button
            type="button"
            class="print-btn"
            onclick="window.print()"
        >
            <i class="fa-solid fa-print"></i>
            พิมพ์ / บันทึก PDF
        </button>

    </div>

</section>


<!-- =========================================
     FILTER
========================================= -->

<form
    method="GET"
    class="report-filter no-print"
>

    <div class="report-filter-group">

        <label>
            สถานะสมาชิก
        </label>

        <select name="status">

            <option
                value="current"
                <?= $statusFilter === 'current' ? 'selected' : '' ?>
            >
                สมาชิกปัจจุบัน
            </option>

            <option
                value="all"
                <?= $statusFilter === 'all' ? 'selected' : '' ?>
            >
                ทั้งหมด
            </option>

            <option
                value="graduated"
                <?= $statusFilter === 'graduated' ? 'selected' : '' ?>
            >
                พ้นสภาพสมาชิก
            </option>

            <option
                value="inactive"
                <?= $statusFilter === 'inactive' ? 'selected' : '' ?>
            >
                ไม่ใช้งาน
            </option>

            <option
                value="suspended"
                <?= $statusFilter === 'suspended' ? 'selected' : '' ?>
            >
                ระงับสมาชิก
            </option>

        </select>

    </div>


    <div class="report-filter-group">

        <label>
            ชั้นปี
        </label>

        <select name="year">

            <option value="">
                ทุกชั้นปี
            </option>

            <option
                value="1"
                <?= $yearFilter === '1' ? 'selected' : '' ?>
            >
                ชั้นปีที่ 1
            </option>

            <option
                value="2"
                <?= $yearFilter === '2' ? 'selected' : '' ?>
            >
                ชั้นปีที่ 2
            </option>

            <option
                value="3"
                <?= $yearFilter === '3' ? 'selected' : '' ?>
            >
                ชั้นปีที่ 3
            </option>

            <option
                value="4"
                <?= $yearFilter === '4' ? 'selected' : '' ?>
            >
                ชั้นปีที่ 4
            </option>

        </select>

    </div>


    <button
        type="submit"
        class="primary-btn"
    >
        <i class="fa-solid fa-filter"></i>
        แสดงรายงาน
    </button>


    <a
        href="members.php"
        class="secondary-btn"
    >
        ล้างตัวกรอง
    </a>

</form>


<!-- =========================================
     รายงานสำหรับ Preview / Print
========================================= -->

<section class="member-report">


    <!-- =====================================
         หัวรายงาน
    ====================================== -->

    <div class="report-header">

        <div class="report-logo">

            <img
                src="<?= BC_BASE_PATH ?>/assets/images/logo.png"
                alt="Bizcomp Club"
            >

        </div>


        <div class="report-title">

            <h1>
                รายงานสรุปสมาชิก ชมรมบิตคอม
            </h1>

            <p>
                คณะการบัญชีและการจัดการ
                มหาวิทยาลัยมหาสารคาม
            </p>

            <span>
                ปีการศึกษา
                <?= (int)$currentAcademicYear ?>
            </span>

        </div>

    </div>


    <!-- =====================================
         ข้อมูลวันที่
    ====================================== -->

    <div class="report-information">

        <span>
            จัดทำเมื่อ
            <?= htmlspecialchars($reportDate) ?>
        </span>


        <span>
            จำนวนสมาชิกทั้งหมด
            <?= number_format($reportTotal) ?>
            คน
        </span>

    </div>


    <!-- =====================================
         SUMMARY
         แสดงบนหน้าจอ แต่ซ่อนตอนพิมพ์
    ====================================== -->

    <div class="report-summary screen-summary">

        <div class="report-summary-item main">

            <span>
                สมาชิกปัจจุบัน
            </span>

            <strong>
                <?= number_format($summary['current']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


        <div class="report-summary-item">

            <span>
                ปี 1
            </span>

            <strong>
                <?= number_format($summary['year1']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


        <div class="report-summary-item">

            <span>
                ปี 2
            </span>

            <strong>
                <?= number_format($summary['year2']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


        <div class="report-summary-item">

            <span>
                ปี 3
            </span>

            <strong>
                <?= number_format($summary['year3']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


        <div class="report-summary-item">

            <span>
                ปี 4
            </span>

            <strong>
                <?= number_format($summary['year4']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>


        <div class="report-summary-item">

            <span>
                พ้นสภาพ
            </span>

            <strong>
                <?= number_format($summary['graduated']) ?>
            </strong>

            <small>
                คน
            </small>

        </div>

    </div>


    <!-- =====================================
         หัวข้อรายชื่อสมาชิก
    ====================================== -->

    <div class="report-section-title">
        รายชื่อสมาชิก
    </div>


    <!-- =====================================
         TABLE
    ====================================== -->

    <div class="report-table-wrap">

        <table class="report-table">

            <thead>

                <tr>

                    <th class="number-column">
                        ลำดับ
                    </th>

                    <th>
                        รหัสนิสิต
                    </th>

                    <th>
                        ชื่อ-นามสกุล
                    </th>

                    <th>
                        ปีที่เข้า
                    </th>

                    <th>
                        ชั้นปี
                    </th>

                    <th>
                        สาขา
                    </th>

                    <th>
                        เบอร์โทร
                    </th>

                    <th>
                        สถานะ
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if ($reportMembers): ?>


                <?php foreach (
                    $reportMembers as $index => $member
                ): ?>

                    <tr>

                        <td class="number-column">
                            <?= $index + 1 ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $member['student_id']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $member['first_name']
                                . ' '
                                . $member['last_name']
                            ) ?>
                        </td>


                        <td>

                            <?= $member['calculated_admission_year']
                                ? (int)$member['calculated_admission_year']
                                : '-'
                            ?>

                        </td>


                        <td>

                            <?php if (
                                $member['calculated_status']
                                === 'graduated'
                            ): ?>

                                พ้นสภาพ

                            <?php elseif (
                                $member['calculated_year'] !== null
                                &&
                                $member['calculated_year'] >= 1
                                &&
                                $member['calculated_year'] <= 4
                            ): ?>

                                ปี
                                <?= (int)$member['calculated_year'] ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $member['major'] ?: '-'
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $member['phone'] ?: '-'
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $member['calculated_status_text']
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        class="report-empty"
                    >
                        ไม่พบข้อมูลสมาชิกตามเงื่อนไขที่เลือก
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>


            <!-- =================================
                 รวมท้ายตาราง
            ================================== -->

            <?php if ($reportMembers): ?>

                <tfoot>

                    <tr class="report-total-row">

                        <td
                            colspan="7"
                            class="report-total-label"
                        >
                            รวมสมาชิกทั้งหมด
                        </td>

                        <td class="report-total-value">

                            <?= number_format($reportTotal) ?>
                            คน

                        </td>

                    </tr>

                </tfoot>

            <?php endif; ?>

        </table>

    </div>


    <!-- =====================================
         สรุปข้อมูลสมาชิกแบบเอกสาร
         แสดงเฉพาะตอนพิมพ์
    ====================================== -->

    <div class="print-member-summary">

        <h3>
            สรุปข้อมูลสมาชิก
        </h3>


        <p>
            จากรายชื่อสมาชิกข้างต้น พบว่า ปัจจุบันชมรมบิตคอม
            มีสมาชิกทั้งหมด
            <strong>
                <?= number_format($summary['current']) ?>
            </strong>
            คน
        </p>


        <p>
            สมาชิกชั้นปีที่ 1 จำนวน
            <strong>
                <?= number_format($summary['year1']) ?>
            </strong>
            คน
        </p>


        <p>
            สมาชิกชั้นปีที่ 2 จำนวน
            <strong>
                <?= number_format($summary['year2']) ?>
            </strong>
            คน
        </p>


        <p>
            สมาชิกชั้นปีที่ 3 จำนวน
            <strong>
                <?= number_format($summary['year3']) ?>
            </strong>
            คน
        </p>


        <p>
            สมาชิกชั้นปีที่ 4 จำนวน
            <strong>
                <?= number_format($summary['year4']) ?>
            </strong>
            คน
        </p>


        <p>
            สมาชิกที่จบการศึกษา จำนวน
            <strong>
                <?= number_format($summary['graduated']) ?>
            </strong>
            คน
        </p>

    </div>


    <!-- =====================================
         SIGNATURE
    ====================================== -->

    <div class="report-signatures">

        <div class="signature-box">

            <div class="signature-line"></div>

            <strong>
                ผู้จัดทำรายงาน
            </strong>

            <span>
                ผู้ดูแลระบบชมรมบิตคอม
            </span>

        </div>


        <div class="signature-box">

            <div class="signature-line"></div>

            <strong>
                ผู้ตรวจสอบ
            </strong>

            <span>
                ................................
            </span>

        </div>

    </div>


    <!-- =====================================
         Footer ตอนพิมพ์
    ====================================== -->

    <div class="report-footer">

        ชมรมบิตคอม ·
        คณะการบัญชีและการจัดการ ·
        มหาวิทยาลัยมหาสารคาม

    </div>

</section>


<style>

/* =========================================
   หัวข้อรายชื่อสมาชิก
========================================= */

.report-section-title {
    margin: 5px 0 10px;
    color: #173d70;
    font-size: 13px;
    font-weight: 600;
}


/* =========================================
   แถวรวม
========================================= */

.report-table tfoot td {
    padding: 10px 8px;
    border: 1px solid #dfe5ed;
    background: #f3f6fa;
    color: #2f435d;
    font-size: 9px;
    font-weight: 600;
}

.report-total-label {
    text-align: right;
}

.report-total-value {
    text-align: center;
    white-space: nowrap;
}


/* =========================================
   สรุปข้อมูลสมาชิก
========================================= */

.print-member-summary {
    margin-top: 24px;
    padding-top: 8px;
}

.print-member-summary h3 {
    margin: 0 0 10px;
    color: #26384d;
    font-size: 13px;
    font-weight: 600;
}

.print-member-summary p {
    margin: 0 0 6px 0;
    color: #44556a;
    font-size: 10px;
    line-height: 1.7;
    text-align: left;
}

.print-member-summary strong {
    color: inherit;
    font-weight: 600;
}


/* =========================================
   ตอนแสดงหน้าจอ
========================================= */

@media screen {

    .print-member-summary {
        display: none;
    }

}


/* =========================================
   PRINT / SAVE PDF
========================================= */

@media print {

    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    /* =====================================
       ซ่อนกล่องสรุปด้านบน
    ====================================== */

    .screen-summary {
        display: none !important;
    }


    /* =====================================
       ข้อมูลวันที่
    ====================================== */

    .report-information {
        margin-bottom: 16px;
    }


    /* =====================================
       หัวข้อรายชื่อสมาชิก
    ====================================== */

    .report-section-title {
        margin-top: 5px;
        margin-bottom: 8px;

        color: #000000 !important;

        font-size: 11px;
        font-weight: 600;
    }


    /* =====================================
       ตาราง
    ====================================== */

    .report-table-wrap {
        overflow: visible;
    }

    .report-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
    }

    .report-table th,
    .report-table td {
        padding: 4px 3px;
        overflow-wrap: anywhere;
        white-space: normal;
        vertical-align: top;
    }

    .report-table th {
        font-size: 8px;
    }

    .report-table td {
        font-size: 8px;
    }

    .report-table th:nth-child(1) { width: 5%; }
    .report-table th:nth-child(2) { width: 12%; }
    .report-table th:nth-child(3) { width: 21%; }
    .report-table th:nth-child(4) { width: 9%; }
    .report-table th:nth-child(5) { width: 7%; }
    .report-table th:nth-child(6) { width: 18%; }
    .report-table th:nth-child(7) { width: 16%; }
    .report-table th:nth-child(8) { width: 12%; }

    .report-table thead {
        display: table-header-group;
    }

    .report-table tr {
        break-inside: avoid;
    }


    /* =====================================
       แถวรวม
    ====================================== */

    .report-table tfoot {
        display: table-row-group;
    }

    .report-table tfoot td {

        background: #ffffff !important;

        color: #000000 !important;

        border: 1px solid #aeb7c2;

        font-size: 8.5px;

        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .report-total-label {
        text-align: right;
        font-weight: 600;
    }

    .report-total-value {
        text-align: center;
        font-weight: 600;
    }


    /* =====================================
       สรุปข้อมูลสมาชิกด้านล่าง
    ====================================== */

    .print-member-summary {

        display: block !important;

        margin-top: 20px;

        padding-top: 8px;

        page-break-inside: avoid;
    }


    .print-member-summary h3 {

        margin: 0 0 8px 0;

        color: #000000 !important;

        font-size: 11px;
        font-weight: 600;
    }


    .print-member-summary p {

        display: block;

        width: 100%;

        margin: 0 0 5px 0;

        color: #000000 !important;

        font-size: 9.5px;

        line-height: 1.7;

        text-align: left;
    }


    .print-member-summary strong {

        color: #000000 !important;

        font-weight: 600;
    }


    /* =====================================
       ลายเซ็น
    ====================================== */

    .report-signatures {

        margin-top: 48px !important;

        page-break-inside: avoid;
    }


    .signature-box strong,
    .signature-box span {

        color: #000000 !important;
    }

}

</style>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
