<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// applications.php หน้านี้ใช้สำหรับจัดการคำขอสมัครสมาชิก ดูรายละเอียด ดูสลิป และแก้ไขข้อมูล

require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'คำขอสมัครสมาชิก';

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedStatuses = [
    '',
    'pending',
    'waiting_payment',
    'checking_payment',
    'approved',
    'rejected'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$success = $_SESSION['application_success'] ?? '';
$error = $_SESSION['application_error'] ?? '';

unset($_SESSION['application_success']);
unset($_SESSION['application_error']);

if (empty($_SESSION['membership_admin_csrf'])) {
    $_SESSION['membership_admin_csrf'] = bin2hex(random_bytes(32));
}

$membershipAdminCsrf = $_SESSION['membership_admin_csrf'];


/* =========================================
   แก้ไขข้อมูลคำขอสมัครสมาชิก
========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form_action'] ?? '') === 'edit_application'
) {
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $studentId = trim($_POST['student_id'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $yearLevel = (int)($_POST['year_level'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $normalizedPhone = normalizePhoneNumber($phone);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        $csrfToken === '' ||
        !hash_equals($membershipAdminCsrf, $csrfToken)
    ) {
        $_SESSION['application_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        header('Location: applications.php');
        exit;
    }

    if (
        $applicationId <= 0 ||
        $studentId === '' ||
        $yearLevel < 1 ||
        $yearLevel > 4 ||
        $normalizedPhone === false
    ) {
        $_SESSION['application_error'] = $normalizedPhone === false
            ? 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก'
            : 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: applications.php');
        exit;
    }

    $phone = $normalizedPhone ?? '';

    try {
        $oldStmt = $pdo->prepare("
            SELECT student_id, faculty, major, year_level, phone
            FROM membership_applications
            WHERE id = ?
            LIMIT 1
        ");
        $oldStmt->execute([$applicationId]);
        $oldApplication = $oldStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldApplication) {
            throw new RuntimeException('ไม่พบคำขอสมัครสมาชิก');
        }

        $duplicateStmt = $pdo->prepare("
            SELECT student_id FROM members WHERE student_id = ?
            UNION ALL
            SELECT student_id
            FROM membership_applications
            WHERE student_id = ?
              AND id != ?
              AND application_status != 'rejected'
            LIMIT 1
        ");
        $duplicateStmt->execute([$studentId, $studentId, $applicationId]);

        if ($duplicateStmt->fetch()) {
            throw new RuntimeException('รหัสนิสิตนี้ถูกใช้งานแล้ว');
        }

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET
                student_id = ?,
                faculty = ?,
                major = ?,
                year_level = ?,
                phone = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $studentId,
            $faculty,
            $major,
            $yearLevel,
            $phone,
            $applicationId
        ]);

        logAdminAction(
            $pdo,
            'update',
            'membership_application',
            $applicationId,
            'แก้ไขข้อมูลคำขอสมัครสมาชิก',
            $oldApplication,
            [
                'student_id' => $studentId,
                'faculty' => $faculty,
                'major' => $major,
                'year_level' => $yearLevel,
                'phone' => $phone
            ]
        );

        $_SESSION['application_success'] =
            'แก้ไขข้อมูลคำขอสมัครสมาชิกเรียบร้อยแล้ว';

    } catch (Throwable $e) {
        $_SESSION['application_error'] = $e->getMessage();
    }

    header('Location: applications.php');
    exit;
}


/* =========================================
   ดึงข้อมูลคำขอ + การชำระเงินล่าสุด
========================================= */

$sql = "
    SELECT
        ma.*,
        u.first_name,
        u.last_name,
        u.email,

        mp.id AS payment_id,
        mp.payment_slip,
        mp.payment_status,
        mp.payment_method,
        mp.amount AS payment_amount

    FROM membership_applications ma

    INNER JOIN users u
        ON u.id = ma.user_id

    LEFT JOIN membership_payments mp
        ON mp.id = (
            SELECT mp2.id
            FROM membership_payments mp2
            WHERE mp2.application_id = ma.id
            ORDER BY mp2.id DESC
            LIMIT 1
        )

    ORDER BY ma.created_at DESC
";

$applications =
    $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/* =========================================
   สรุปจำนวนสถานะ
========================================= */

$summary = [
    'all' => count($applications),
    'pending' => 0,
    'waiting_payment' => 0,
    'checking_payment' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($applications as $item) {
    $itemStatus = $item['application_status'] ?? '';

    if (isset($summary[$itemStatus])) {
        $summary[$itemStatus]++;
    }
}


/* =========================================
   ค้นหา / กรอง
========================================= */

$filteredApplications = [];

foreach ($applications as $application) {
    $applicationStatus =
        $application['application_status'] ?? '';

    if ($search !== '') {
        $searchText =
            ($application['first_name'] ?? '') . ' ' .
            ($application['last_name'] ?? '') . ' ' .
            ($application['student_id'] ?? '') . ' ' .
            ($application['email'] ?? '');

        if (mb_stripos($searchText, $search) === false) {
            continue;
        }
    }

    if (
        $statusFilter !== '' &&
        $applicationStatus !== $statusFilter
    ) {
        continue;
    }

    $filteredApplications[] = $application;
}


/* =========================================
   ฟังก์ชันช่วยแสดงผล
========================================= */

function appE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function appStatusText(string $status): string
{
    return match ($status) {
        'pending' => 'รอตรวจสอบ',
        'waiting_payment' => 'รอชำระเงิน',
        'checking_payment' => 'รอตรวจสอบชำระเงิน',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        default => $status
    };
}

function appPaymentStatusText(?string $status): string
{
    $status = trim((string)$status);

    return match ($status) {
        'pending' => 'รอตรวจสอบ',
        'checking' => 'รอตรวจสอบ',
        'approved' => 'ชำระเงินแล้ว',
        'paid' => 'ชำระเงินแล้ว',
        'rejected' => 'ไม่ผ่าน',
        '' => 'ยังไม่มีข้อมูล',
        default => $status
    };
}

function appPaymentMethodText(?string $method): string
{
    $method = trim((string)$method);

    return match ($method) {
        'promptpay' => 'QR / พร้อมเพย์',
        'bank_transfer' => 'โอนผ่านธนาคาร',
        '' => '-',
        default => $method
    };
}

function appSlipUrl(int $paymentId, ?string $path): string
{
    $path = trim((string)$path);

    if ($paymentId <= 0 || $path === '') {
        return '';
    }

    return '../../payment_slip_view.php?payment_id=' . $paymentId;
}


/* =========================================
   Header / Sidebar
========================================= */

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<link
    rel="stylesheet"
    href="../assets/css/applications.css?v=10"
>


<!-- =========================================
     SUMMARY CARDS
========================================== -->

<div class="application-stat-grid">

    <div class="application-stat-card main">
        <span>คำขอทั้งหมด</span>
        <strong><?= $summary['all'] ?></strong>
        <small>รายการ</small>
        <i class="fa-solid fa-users"></i>
    </div>

    <div class="application-stat-card">
        <span>รอตรวจสอบ</span>
        <strong><?= $summary['pending'] ?></strong>
        <small>รายการ</small>
        <i class="fa-solid fa-user-clock"></i>
    </div>

    <div class="application-stat-card">
        <span>รอชำระเงิน</span>
        <strong><?= $summary['waiting_payment'] ?></strong>
        <small>รายการ</small>
        <i class="fa-solid fa-wallet"></i>
    </div>

    <div class="application-stat-card">
        <span>รอตรวจสลิป</span>
        <strong><?= $summary['checking_payment'] ?></strong>
        <small>รายการ</small>
        <i class="fa-solid fa-receipt"></i>
    </div>

    <div class="application-stat-card">
        <span>อนุมัติแล้ว</span>
        <strong><?= $summary['approved'] ?></strong>
        <small>รายการ</small>
        <i class="fa-solid fa-user-check"></i>
    </div>

</div>


<!-- =========================================
     MEMBERSHIP TABLE
========================================== -->

<section class="membership-applications-page">

    <div class="membership-applications-heading">
        <span>MEMBERSHIP</span>
        <h2>คำขอสมัครสมาชิก</h2>
    </div>


    <?php if ($success): ?>

        <div class="membership-alert success">
            <i class="fa-solid fa-circle-check"></i>
            <?= appE($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="membership-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= appE($error) ?>
        </div>

    <?php endif; ?>


    <form
        method="GET"
        class="membership-filter"
    >

        <div class="membership-search">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="search"
                name="search"
                value="<?= appE($search) ?>"
                placeholder="ค้นหาชื่อ รหัสนิสิต หรืออีเมล"
            >

        </div>


        <select name="status">

            <option value="">
                ทุกสถานะ
            </option>

            <option
                value="pending"
                <?= $statusFilter === 'pending'
                    ? 'selected'
                    : ''
                ?>
            >
                รอตรวจสอบ
            </option>

            <option
                value="waiting_payment"
                <?= $statusFilter === 'waiting_payment'
                    ? 'selected'
                    : ''
                ?>
            >
                รอชำระเงิน
            </option>

            <option
                value="checking_payment"
                <?= $statusFilter === 'checking_payment'
                    ? 'selected'
                    : ''
                ?>
            >
                รอตรวจสอบชำระเงิน
            </option>

            <option
                value="approved"
                <?= $statusFilter === 'approved'
                    ? 'selected'
                    : ''
                ?>
            >
                อนุมัติแล้ว
            </option>

            <option
                value="rejected"
                <?= $statusFilter === 'rejected'
                    ? 'selected'
                    : ''
                ?>
            >
                ไม่อนุมัติ
            </option>

        </select>


        <button
            type="submit"
            class="membership-filter-btn"
        >
            <i class="fa-solid fa-filter"></i>
            กรอง
        </button>


        <a
            href="applications.php"
            class="membership-clear-btn"
        >
            ล้าง
        </a>

    </form>


    <div class="membership-table-wrap">

        <table class="membership-applications-table">

            <thead>

                <tr>
                    <th>ผู้สมัคร</th>
                    <th>รหัสนิสิต</th>
                    <th>คณะ / สาขา</th>
                    <th>ชั้นปี</th>
                    <th>เบอร์โทร</th>
                    <th>สถานะ</th>
                    <th>วันที่สมัคร</th>
                    <th class="center">สลิปเงิน</th>
                    <th class="center">จัดการ</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!empty($filteredApplications)): ?>

                    <?php foreach (
                        $filteredApplications
                        as $application
                    ): ?>

                        <?php
                        $fullName =
                            trim(
                                ($application['first_name'] ?? '') .
                                ' ' .
                                ($application['last_name'] ?? '')
                            );

                        if ($fullName === '') {
                            $fullName = 'ไม่ระบุชื่อ';
                        }

                        $firstLetter =
                            mb_substr(
                                $fullName,
                                0,
                                1,
                                'UTF-8'
                            );

                        $paymentSlip =
                            appSlipUrl(
                                (int)($application['payment_id'] ?? 0),
                                $application['payment_slip'] ?? ''
                            );

                        $paymentSlipType =
                            strtolower(
                                pathinfo(
                                    (string)($application['payment_slip'] ?? ''),
                                    PATHINFO_EXTENSION
                                )
                            ) === 'pdf'
                                ? 'pdf'
                                : 'image';

                        $createdAtText =
                            !empty($application['created_at'])
                            ? date(
                                'd/m/Y H:i',
                                strtotime(
                                    $application['created_at']
                                )
                            )
                            : '-';

                        $paymentAmount =
                            number_format(
                                (float)(
                                    $application['payment_amount'] ?? 0
                                ),
                                2
                            );
                        ?>

                        <tr>

                            <td>

                                <div class="membership-applicant">

                                    <div class="membership-avatar">
                                        <?= appE($firstLetter) ?>
                                    </div>

                                    <div class="membership-applicant-text">

                                        <strong>
                                            <?= appE($fullName) ?>
                                        </strong>

                                        <span>
                                            <?= appE(
                                                $application['email'] ?? '-'
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <td>
                                <?= appE(
                                    $application['student_id'] ?? '-'
                                ) ?>
                            </td>


                            <td>

                                <div class="membership-faculty">

                                    <strong>
                                        <?= appE(
                                            $application['faculty'] ?? '-'
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= appE(
                                            $application['major'] ?? '-'
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <td>
                                ปี <?= (int)(
                                    $application['year_level'] ?? 0
                                ) ?>
                            </td>


                            <td>
                                <?= appE(
                                    $application['phone'] ?? '-'
                                ) ?>
                            </td>


                            <td>

                                <span
                                    class="membership-status <?= appE(
                                        $application[
                                            'application_status'
                                        ] ?? ''
                                    ) ?>"
                                >
                                    <?= appE(
                                        appStatusText(
                                            $application[
                                                'application_status'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td class="membership-date-cell">
                                <?= appE($createdAtText) ?>
                            </td>


                            <!-- ไอคอนตา -->
                            <td class="center">

                                <button
                                    type="button"
                                    class="membership-icon-btn slip"
                                    title="ดูรายละเอียด"
                                    data-id="<?= (int)$application['id'] ?>"
                                    data-name="<?= appE($fullName) ?>"
                                    data-email="<?= appE(
                                        $application['email'] ?? '-'
                                    ) ?>"
                                    data-student-id="<?= appE(
                                        $application['student_id'] ?? '-'
                                    ) ?>"
                                    data-faculty="<?= appE(
                                        $application['faculty'] ?? '-'
                                    ) ?>"
                                    data-major="<?= appE(
                                        $application['major'] ?? '-'
                                    ) ?>"
                                    data-year="<?= (int)(
                                        $application['year_level'] ?? 0
                                    ) ?>"
                                    data-phone="<?= appE(
                                        $application['phone'] ?? '-'
                                    ) ?>"
                                    data-created="<?= appE(
                                        $createdAtText
                                    ) ?>"
                                    data-status="<?= appE(
                                        $application[
                                            'application_status'
                                        ] ?? ''
                                    ) ?>"
                                    data-status-text="<?= appE(
                                        appStatusText(
                                            $application[
                                                'application_status'
                                            ] ?? ''
                                        )
                                    ) ?>"
                                    data-payment-status="<?= appE(
                                        $application[
                                            'payment_status'
                                        ] ?? ''
                                    ) ?>"
                                    data-payment-status-text="<?= appE(
                                        appPaymentStatusText(
                                            $application[
                                                'payment_status'
                                            ] ?? ''
                                        )
                                    ) ?>"
                                    data-payment-method="<?= appE(
                                        appPaymentMethodText(
                                            $application[
                                                'payment_method'
                                            ] ?? ''
                                        )
                                    ) ?>"
                                    data-payment-amount="<?= appE(
                                        $paymentAmount
                                    ) ?>"
                                    data-slip="<?= appE($paymentSlip) ?>"
                                    data-slip-type="<?= appE($paymentSlipType) ?>"
                                    onclick="openApplicationDetail(this)"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>

                            </td>


                            <!-- ไอคอนดินสอ -->
                            <td class="center">

                                <button
                                    type="button"
                                    class="membership-icon-btn edit"
                                    title="แก้ไข"
                                    data-id="<?= (int)$application['id'] ?>"
                                    data-student-id="<?= appE(
                                        $application['student_id'] ?? ''
                                    ) ?>"
                                    data-faculty="<?= appE(
                                        $application['faculty'] ?? ''
                                    ) ?>"
                                    data-major="<?= appE(
                                        $application['major'] ?? ''
                                    ) ?>"
                                    data-year="<?= (int)(
                                        $application['year_level'] ?? 1
                                    ) ?>"
                                    data-phone="<?= appE(
                                        $application['phone'] ?? ''
                                    ) ?>"
                                    onclick="openApplicationEdit(this)"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            class="membership-empty"
                        >
                            ไม่พบคำขอสมัครสมาชิก
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =========================================
     DETAIL DRAWER
========================================== -->

<div
    class="membership-detail-overlay"
    id="applicationDetailDrawer"
    aria-hidden="true"
>

    <div
        class="membership-detail-backdrop"
        onclick="closeApplicationDetail()"
    ></div>


    <aside class="membership-detail-panel">

        <div class="membership-detail-head">

            <h3>
                รายละเอียดคำขอสมัครสมาชิก
            </h3>

            <button
                type="button"
                class="membership-detail-close"
                onclick="closeApplicationDetail()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <div class="membership-detail-scroll">

            <section class="membership-detail-card">

                <h4>ข้อมูลผู้สมัคร</h4>

                <div class="membership-detail-row">
                    <span>ชื่อ - นามสกุล</span>
                    <b>:</b>
                    <strong id="detailName">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>อีเมล</span>
                    <b>:</b>
                    <strong id="detailEmail">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>รหัสนิสิต</span>
                    <b>:</b>
                    <strong id="detailStudentId">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>คณะ</span>
                    <b>:</b>
                    <strong id="detailFaculty">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>สาขา</span>
                    <b>:</b>
                    <strong id="detailMajor">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>ชั้นปี</span>
                    <b>:</b>
                    <strong id="detailYear">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>เบอร์โทรศัพท์</span>
                    <b>:</b>
                    <strong id="detailPhone">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>วันที่สมัคร</span>
                    <b>:</b>
                    <strong id="detailCreated">-</strong>
                </div>

                <div class="membership-detail-row">
                    <span>สถานะคำขอ</span>
                    <b>:</b>

                    <strong>
                        <span
                            class="membership-detail-status"
                            id="detailStatus"
                        >
                            -
                        </span>
                    </strong>
                </div>

                <div class="membership-detail-row">
                    <span>สถานะการชำระเงิน</span>
                    <b>:</b>

                    <strong>
                        <span
                            class="membership-detail-payment"
                            id="detailPaymentStatus"
                        >
                            -
                        </span>
                    </strong>
                </div>

            </section>


            <section class="membership-detail-card">

                <h4>สลิปการชำระเงิน</h4>

                <div
                    class="membership-slip-empty"
                    id="detailSlipEmpty"
                >
                    <i class="fa-regular fa-image"></i>
                    <span>ยังไม่มีสลิปการชำระเงิน</span>
                </div>

                <div
                    class="membership-slip-preview"
                    id="detailSlipPreview"
                >

                    <img
                        src=""
                        alt="สลิปการชำระเงิน"
                        id="detailSlipImage"
                    >

                    <iframe
                        src=""
                        title="สลิปการชำระเงิน"
                        id="detailSlipPdf"
                    ></iframe>

                </div>


                <div class="membership-payment-meta">

                    <div>
                        <span>จำนวนเงิน</span>

                        <strong>
                            <span id="detailPaymentAmount">
                                0.00
                            </span>
                            บาท
                        </strong>
                    </div>

                    <div>
                        <span>ช่องทางชำระเงิน</span>

                        <strong id="detailPaymentMethod">
                            -
                        </strong>
                    </div>

                </div>

            </section>

        </div>


        <div
            class="membership-detail-actions"
            id="detailActions"
        >

            <form
                action="update_status.php"
                method="POST"
                data-bc-confirm
                data-bc-confirm-type="approve"
                data-bc-confirm-title="อนุมัติคำขอสมาชิก"
                data-bc-confirm-message="ยืนยันคำขอสมัครสมาชิกนี้หรือไม่?"
                data-bc-confirm-label="ยืนยัน"
            >

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($membershipAdminCsrf) ?>">

                <input
                    type="hidden"
                    name="application_id"
                    id="detailApproveId"
                >

                <input
                    type="hidden"
                    name="action"
                    value="approve"
                >

                <button
                    type="submit"
                    class="membership-detail-btn approve"
                >
                    <i class="fa-regular fa-circle-check"></i>
                    ยืนยัน
                </button>

            </form>


            <div>
                <input
                    type="hidden"
                    id="detailRejectId"
                >

                <button
                    type="button"
                    class="membership-detail-btn reject"
                    onclick="openApplicationReject()"
                >
                    <i class="fa-regular fa-circle-xmark"></i>
                    ปฏิเสธ
                </button>

            </div>

        </div>

    </aside>

</div>


<!-- =========================================
     EDIT MODAL
========================================== -->

<div
    class="membership-edit-overlay"
    id="applicationEditModal"
    aria-hidden="true"
>

    <div class="membership-edit-modal">

        <div class="membership-edit-head">

            <div>
                <small>EDIT APPLICATION</small>
                <h3>แก้ไขข้อมูลคำขอสมัครสมาชิก</h3>
            </div>

            <button
                type="button"
                class="membership-edit-close"
                onclick="closeApplicationEdit()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($membershipAdminCsrf) ?>">

            <input
                type="hidden"
                name="form_action"
                value="edit_application"
            >

            <input
                type="hidden"
                name="application_id"
                id="editApplicationId"
            >


            <div class="membership-edit-grid">

                <label>

                    <span>รหัสนิสิต</span>

                    <input
                        type="text"
                        name="student_id"
                        id="editStudentId"
                        required
                    >

                </label>


                <label>

                    <span>ชั้นปี</span>

                    <select
                        name="year_level"
                        id="editYearLevel"
                        required
                    >
                        <option value="1">ปี 1</option>
                        <option value="2">ปี 2</option>
                        <option value="3">ปี 3</option>
                        <option value="4">ปี 4</option>
                    </select>

                </label>


                <label>

                    <span>คณะ</span>

                    <input
                        type="text"
                        name="faculty"
                        id="editFaculty"
                    >

                </label>


                <label>

                    <span>สาขา</span>

                    <input
                        type="text"
                        name="major"
                        id="editMajor"
                    >

                </label>


                <label class="full">

                    <span>เบอร์โทร</span>

                    <input
                        type="text"
                        name="phone"
                        id="editPhone"
                    >

                </label>

            </div>


            <div class="membership-edit-actions">

                <button
                    type="button"
                    class="membership-modal-cancel"
                    onclick="closeApplicationEdit()"
                >
                    ยกเลิก
                </button>

                <button
                    type="submit"
                    class="membership-modal-save"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    บันทึกการแก้ไข
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================
     REJECT REASON MODAL
========================================== -->

<div
    class="membership-edit-overlay"
    id="applicationRejectModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="applicationRejectTitle"
    aria-hidden="true"
>

    <div class="membership-edit-modal">

        <div class="membership-edit-head">

            <div>
                <small>REJECT APPLICATION</small>
                <h3 id="applicationRejectTitle">
                    เหตุผลการปฏิเสธคำขอสมัครสมาชิก
                </h3>
            </div>

            <button
                type="button"
                class="membership-edit-close"
                onclick="closeApplicationReject()"
                aria-label="ปิดกล่องเหตุผลการปฏิเสธ"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form
            action="update_status.php"
            method="POST"
            id="applicationRejectForm"
        >

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($membershipAdminCsrf) ?>">
            <input type="hidden" name="application_id" id="rejectApplicationId">
            <input type="hidden" name="action" value="reject">

            <div class="membership-edit-grid">

                <label class="full">
                    <span>ระบุเหตุผลการปฏิเสธ *</span>

                    <textarea
                        name="reject_reason"
                        id="applicationRejectReason"
                        rows="6"
                        maxlength="2000"
                        required
                        placeholder="กรอกเหตุผลหรือสิ่งที่ผู้สมัครต้องแก้ไข"
                        style="width:100%;padding:10px;color:#34445c;background:#fff;border:1px solid #d9e0e9;border-radius:8px;outline:none;font-family:'Prompt',sans-serif;font-size:10px;line-height:1.6;resize:vertical;box-sizing:border-box;"
                    ></textarea>
                </label>

            </div>


            <div class="membership-edit-actions">

                <button
                    type="button"
                    class="membership-modal-cancel"
                    onclick="closeApplicationReject()"
                >
                    ยกเลิก
                </button>

                <button
                    type="submit"
                    class="membership-modal-save"
                    style="background:#ef1727;border-color:#ef1727;"
                >
                    <i class="fa-regular fa-circle-xmark"></i>
                    ยืนยันการปฏิเสธ
                </button>

            </div>

        </form>

    </div>

</div>


<script>
function openApplicationDetail(button) {

    const drawer =
        document.getElementById(
            'applicationDetailDrawer'
        );

    const status =
        button.dataset.status || '';

    const paymentStatus =
        button.dataset.paymentStatus || '';

    const slip =
        button.dataset.slip || '';

    const slipType =
        button.dataset.slipType || 'image';


    document.getElementById(
        'detailName'
    ).textContent =
        button.dataset.name || '-';

    document.getElementById(
        'detailEmail'
    ).textContent =
        button.dataset.email || '-';

    document.getElementById(
        'detailStudentId'
    ).textContent =
        button.dataset.studentId || '-';

    document.getElementById(
        'detailFaculty'
    ).textContent =
        button.dataset.faculty || '-';

    document.getElementById(
        'detailMajor'
    ).textContent =
        button.dataset.major || '-';

    document.getElementById(
        'detailYear'
    ).textContent =
        button.dataset.year
            ? 'ปี ' + button.dataset.year
            : '-';

    document.getElementById(
        'detailPhone'
    ).textContent =
        button.dataset.phone || '-';

    document.getElementById(
        'detailCreated'
    ).textContent =
        button.dataset.created || '-';


    const statusBadge =
        document.getElementById(
            'detailStatus'
        );

    statusBadge.textContent =
        button.dataset.statusText || '-';

    statusBadge.className =
        'membership-detail-status ' +
        status;


    const paymentBadge =
        document.getElementById(
            'detailPaymentStatus'
        );

    paymentBadge.textContent =
        button.dataset.paymentStatusText || '-';

    paymentBadge.className =
        'membership-detail-payment ' +
        paymentStatus;


    document.getElementById(
        'detailPaymentAmount'
    ).textContent =
        button.dataset.paymentAmount || '0.00';

    document.getElementById(
        'detailPaymentMethod'
    ).textContent =
        button.dataset.paymentMethod || '-';


    const slipEmpty =
        document.getElementById(
            'detailSlipEmpty'
        );

    const slipPreview =
        document.getElementById(
            'detailSlipPreview'
        );

    const slipImage =
        document.getElementById(
            'detailSlipImage'
        );

    const slipPdf =
        document.getElementById(
            'detailSlipPdf'
        );


    slipImage.src = '';
    slipPdf.src = '';

    slipImage.style.display = 'none';
    slipPdf.style.display = 'none';


    if (slip !== '') {

        slipEmpty.style.display = 'none';
        slipPreview.style.display = 'block';

        if (slipType === 'pdf') {

            slipPdf.src = slip;
            slipPdf.style.display = 'block';

        } else {

            slipImage.src = slip;
            slipImage.style.display = 'block';
        }

    } else {

        slipEmpty.style.display = 'flex';
        slipPreview.style.display = 'none';
    }


    document.getElementById(
        'detailApproveId'
    ).value =
        button.dataset.id || '';

    document.getElementById(
        'detailRejectId'
    ).value =
        button.dataset.id || '';


    const actionArea =
        document.getElementById(
            'detailActions'
        );

    if (
        status === 'pending' ||
        status === 'checking_payment'
    ) {
        actionArea.style.display = 'grid';
    } else {
        actionArea.style.display = 'none';
    }


    drawer.classList.add('show');

    drawer.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';
}


function closeApplicationDetail() {

    const drawer =
        document.getElementById(
            'applicationDetailDrawer'
        );

    drawer.classList.remove('show');

    drawer.setAttribute(
        'aria-hidden',
        'true'
    );

    document.getElementById(
        'detailSlipImage'
    ).src = '';

    document.getElementById(
        'detailSlipPdf'
    ).src = '';

    document.body.style.overflow = '';
}


function openApplicationEdit(button) {

    const modal =
        document.getElementById(
            'applicationEditModal'
        );

    document.getElementById(
        'editApplicationId'
    ).value =
        button.dataset.id || '';

    document.getElementById(
        'editStudentId'
    ).value =
        button.dataset.studentId || '';

    document.getElementById(
        'editFaculty'
    ).value =
        button.dataset.faculty || '';

    document.getElementById(
        'editMajor'
    ).value =
        button.dataset.major || '';

    document.getElementById(
        'editYearLevel'
    ).value =
        button.dataset.year || '1';

    document.getElementById(
        'editPhone'
    ).value =
        button.dataset.phone || '';

    modal.classList.add('show');

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';
}


function closeApplicationEdit() {

    const modal =
        document.getElementById(
            'applicationEditModal'
        );

    modal.classList.remove('show');

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.style.overflow = '';
}


function openApplicationReject() {

    const applicationId =
        document.getElementById(
            'detailRejectId'
        ).value;

    if (!applicationId) {
        return;
    }

    const modal =
        document.getElementById(
            'applicationRejectModal'
        );

    const reason =
        document.getElementById(
            'applicationRejectReason'
        );

    document.getElementById(
        'rejectApplicationId'
    ).value = applicationId;

    reason.value = '';
    reason.setCustomValidity('');

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';

    window.setTimeout(function () {
        reason.focus();
    }, 50);
}


function closeApplicationReject() {

    const modal =
        document.getElementById(
            'applicationRejectModal'
        );

    const reason =
        document.getElementById(
            'applicationRejectReason'
        );

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');

    reason.value = '';
    reason.setCustomValidity('');
}


const applicationRejectForm =
    document.getElementById(
        'applicationRejectForm'
    );

const applicationRejectReason =
    document.getElementById(
        'applicationRejectReason'
    );

applicationRejectReason.addEventListener(
    'input',
    function () {
        applicationRejectReason.setCustomValidity('');
    }
);

applicationRejectForm.addEventListener(
    'submit',
    function (event) {

        const reason =
            applicationRejectReason.value.trim();

        if (reason === '') {
            event.preventDefault();

            applicationRejectReason.setCustomValidity(
                'กรุณากรอกเหตุผลการปฏิเสธ'
            );

            applicationRejectReason.reportValidity();
            return;
        }

        applicationRejectReason.setCustomValidity('');
        applicationRejectReason.value = reason;
    }
);


document.addEventListener(
    'keydown',
    function (event) {

        if (event.key !== 'Escape') {
            return;
        }

        const rejectModal =
            document.getElementById(
                'applicationRejectModal'
            );

        if (rejectModal.classList.contains('show')) {
            closeApplicationReject();
            return;
        }

        closeApplicationDetail();
        closeApplicationEdit();
    }
);
</script>


<?php
include __DIR__ . '/../includes/confirmation_modal.php';
include __DIR__ . '/../includes/admin_footer.php';
?>
