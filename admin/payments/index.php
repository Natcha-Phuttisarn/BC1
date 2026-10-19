<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// index.php
// หน้านี้ใช้สำหรับตรวจสอบหลักฐานการชำระค่าสมัครสมาชิก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/db.php';
require_once '../includes/admin_auth.php';

$pageTitle = 'ตรวจสอบการชำระเงิน';
$adminPageTitle = 'ตรวจสอบการชำระเงิน';


// =========================================
// CSRF
// =========================================

if (empty($_SESSION['admin_payment_csrf'])) {
    $_SESSION['admin_payment_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_payment_csrf'];


// =========================================
// MESSAGE
// =========================================

$success = $_SESSION['payment_admin_success'] ?? '';
$error = $_SESSION['payment_admin_error'] ?? '';

unset($_SESSION['payment_admin_success']);
unset($_SESSION['payment_admin_error']);


// =========================================
// FILTER
// =========================================

$status = trim($_GET['status'] ?? '');

$allowedStatus = [
    '',
    'pending',
    'approved',
    'rejected'
];

if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}


// =========================================
// ดึงข้อมูลการชำระเงิน
// =========================================

$sql = "
    SELECT
        p.id,
        p.application_id,
        p.user_id,
        p.amount,
        p.payment_method,
        p.payment_slip,
        p.payment_status,
        p.admin_note AS payment_admin_note,
        p.paid_at,

        a.student_id,
        a.faculty,
        a.major,
        a.year_level,
        a.gender,
        a.phone,
        a.application_status,
        a.admin_note AS application_admin_note,
        a.applied_at,

        u.first_name,
        u.last_name,
        u.email

    FROM membership_payments p

    INNER JOIN membership_applications a
        ON a.id = p.application_id

    INNER JOIN users u
        ON u.id = p.user_id
";

$params = [];

if ($status !== '') {

    $sql .= "
        WHERE p.payment_status = ?
    ";

    $params[] = $status;
}

$sql .= "
    ORDER BY
        CASE
            WHEN p.payment_status = 'pending' THEN 1
            WHEN p.payment_status = 'rejected' THEN 2
            ELSE 3
        END,
        p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$payments = $stmt->fetchAll();


// =========================================
// จำนวนแต่ละสถานะ
// =========================================

$countStmt = $pdo->query("
    SELECT
        SUM(payment_status = 'pending') AS pending_count,
        SUM(payment_status = 'approved') AS approved_count,
        SUM(payment_status = 'rejected') AS rejected_count,
        COUNT(*) AS total_count
    FROM membership_payments
");

$counts = $countStmt->fetch();


// =========================================
// FUNCTION
// =========================================

function paymentStatusText(string $status): string
{
    return match ($status) {

        'pending' =>
            'รอตรวจสอบ',

        'approved' =>
            'อนุมัติแล้ว',

        'rejected' =>
            'ไม่อนุมัติ',

        default =>
            $status
    };
}


function paymentMethodText(string $method): string
{
    return match ($method) {

        'promptpay' =>
            'พร้อมเพย์',

        'bank_transfer' =>
            'โอนผ่านธนาคาร',

        default =>
            $method
    };
}


include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>


<style>

/* =========================================
   PAYMENT ADMIN
========================================= */

.payment-admin-page{
    width:100%;
    max-width:1180px;
    margin:0 auto;
}


/* =========================================
   HEADING
========================================= */

.payment-page-head{
    margin-bottom:24px;

    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
}

.payment-page-head span{
    color:#1768cf;
    font-size:10px;
    font-weight:600;
    letter-spacing:1.3px;
}

.payment-page-head h1{
    margin:4px 0;

    color:#24496e;

    font-size:27px;
    font-weight:600;
}

.payment-page-head p{
    margin:0;

    color:#8797a8;

    font-size:11px;
}


/* =========================================
   ALERT
========================================= */

.payment-alert{
    margin-bottom:18px;
    padding:13px 16px;

    border-radius:10px;

    display:flex;
    gap:9px;
    align-items:center;

    font-size:11px;
}

.payment-alert.success{
    color:#237449;
    background:#edf9f2;
    border:1px solid #caead8;
}

.payment-alert.error{
    color:#b33f3f;
    background:#fff1f1;
    border:1px solid #efcece;
}


/* =========================================
   SUMMARY
========================================= */

.payment-summary{
    margin-bottom:22px;

    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
}

.payment-summary-card{
    padding:18px;

    background:#fff;

    border:1px solid #e0e7ef;
    border-radius:13px;
}

.payment-summary-card span{
    color:#8292a3;

    display:block;

    font-size:10px;
}

.payment-summary-card strong{
    margin-top:3px;

    color:#264c71;

    display:block;

    font-size:25px;
    font-weight:600;
}


/* =========================================
   FILTER
========================================= */

.payment-filter{
    margin-bottom:20px;

    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.payment-filter a{
    min-height:37px;
    padding:0 13px;

    border:1px solid #dbe3ec;
    border-radius:8px;

    background:#fff;
    color:#657b90;

    display:inline-flex;
    align-items:center;

    font-size:9px;
    font-weight:500;

    text-decoration:none;
}

.payment-filter a.active{
    border-color:#1768cf;

    background:#1768cf;
    color:#fff;
}


/* =========================================
   PAYMENT CARD
========================================= */

.payment-card{
    margin-bottom:18px;
    padding:22px;

    background:#fff;

    border:1px solid #e0e7ef;
    border-radius:14px;

    box-shadow:
        0 5px 18px rgba(30,60,90,.04);
}

.payment-card-top{
    margin-bottom:18px;
    padding-bottom:16px;

    border-bottom:1px solid #edf1f5;

    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:15px;
}

.payment-person{
    display:flex;
    align-items:center;
    gap:12px;
}

.payment-person-avatar{
    width:45px;
    height:45px;

    border-radius:50%;

    background:#eaf3ff;
    color:#1768cf;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:16px;
    font-weight:600;
}

.payment-person h3{
    margin:0;

    color:#304f6e;

    font-size:14px;
    font-weight:600;
}

.payment-person p{
    margin:3px 0 0;

    color:#8a99a8;

    font-size:9px;
}


/* =========================================
   STATUS
========================================= */

.payment-badge{
    padding:6px 10px;

    border-radius:999px;

    display:inline-flex;
    align-items:center;
    gap:6px;

    font-size:9px;
    font-weight:500;
}

.payment-badge.pending{
    color:#916b12;
    background:#fff4cf;
}

.payment-badge.approved{
    color:#237449;
    background:#e8f7ef;
}

.payment-badge.rejected{
    color:#a74444;
    background:#feecec;
}


/* =========================================
   DETAIL
========================================= */

.payment-body{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px;
}

.payment-information{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
}

.payment-info-box{
    padding:12px;

    border:1px solid #e7edf3;
    border-radius:9px;

    background:#fbfcfe;
}

.payment-info-box span{
    margin-bottom:3px;

    color:#8a98a6;

    display:block;

    font-size:8px;
}

.payment-info-box strong{
    color:#405e7a;

    display:block;

    font-size:10px;
    font-weight:500;

    word-break:break-word;
}


/* =========================================
   SLIP
========================================= */

.payment-slip{
    padding:15px;

    border:1px solid #e4eaf0;
    border-radius:11px;

    background:#fafcff;
}

.payment-slip h4{
    margin:0 0 12px;

    color:#45627e;

    font-size:11px;
}

.payment-slip-image{
    width:100%;
    max-height:350px;

    border-radius:8px;

    display:block;

    object-fit:contain;

    background:#fff;
}

.payment-slip-link{
    min-height:42px;
    padding:0 14px;

    border:1px solid #dce5ee;
    border-radius:8px;

    background:#fff;
    color:#1768cf;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;

    font-size:9px;
    font-weight:500;

    text-decoration:none;
}


/* =========================================
   REVIEW
========================================= */

.payment-review{
    margin-top:20px;
    padding-top:18px;

    border-top:1px solid #e9eef3;
}

.payment-review h4{
    margin:0 0 12px;

    color:#405e7b;

    font-size:11px;
}

.payment-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}


/* Approve */

.approve-form button{
    min-height:42px;
    padding:0 17px;

    border:0;
    border-radius:8px;

    background:#238b55;
    color:#fff;

    font-family:"Prompt",sans-serif;
    font-size:9px;
    font-weight:500;

    cursor:pointer;
}


/* Reject */

.reject-form{
    width:100%;
    margin-top:14px;
    padding:16px;

    border:1px solid #f0dede;
    border-radius:10px;

    background:#fffafa;
}

.reject-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.reject-form label{
    margin-bottom:5px;

    color:#6a5960;

    display:block;

    font-size:9px;
    font-weight:500;
}

.reject-form select,
.reject-form textarea{
    width:100%;

    padding:10px 12px;

    border:1px solid #dfd6d9;
    border-radius:8px;

    background:#fff;

    font-family:"Prompt",sans-serif;
    font-size:9px;

    outline:none;

    box-sizing:border-box;
}

.reject-form textarea{
    min-height:75px;
    resize:vertical;
}

.reject-form button{
    margin-top:11px;
    min-height:40px;
    padding:0 15px;

    border:0;
    border-radius:8px;

    background:#c94d4d;
    color:#fff;

    font-family:"Prompt",sans-serif;
    font-size:9px;

    cursor:pointer;
}


/* =========================================
   REVIEW RESULT
========================================= */

.review-result{
    margin-top:18px;
    padding:14px;

    border-radius:9px;

    font-size:9px;
    line-height:1.8;
}

.review-result.approved{
    color:#276f4a;
    background:#f0faf5;
}

.review-result.rejected{
    color:#984747;
    background:#fff2f2;
}

.review-result strong{
    display:block;
}


/* =========================================
   EMPTY
========================================= */

.payment-empty{
    padding:45px 20px;

    background:#fff;

    border:1px solid #e1e8ef;
    border-radius:13px;

    color:#8797a6;

    text-align:center;
}

.payment-empty i{
    margin-bottom:10px;

    color:#b1bdc9;

    display:block;

    font-size:38px;
}

.payment-empty h3{
    margin:0 0 4px;

    color:#526c84;

    font-size:14px;
}

.payment-empty p{
    margin:0;

    font-size:9px;
}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:900px){

    .payment-summary{
        grid-template-columns:repeat(2,1fr);
    }

    .payment-body{
        grid-template-columns:1fr;
    }

}


@media(max-width:600px){

    .payment-summary{
        grid-template-columns:1fr;
    }

    .payment-information,
    .reject-grid{
        grid-template-columns:1fr;
    }

    .payment-page-head{
        align-items:flex-start;
        flex-direction:column;
    }

}

</style>


<div class="payment-admin-page">


    <!-- =====================================
         HEADING
    ====================================== -->

    <div class="payment-page-head">

        <div>

            <span>
                MEMBERSHIP PAYMENTS
            </span>

            <h1>
                ตรวจสอบการชำระเงิน
            </h1>

            <p>
                ตรวจสลิป อนุมัติ หรือแจ้งเหตุผลเมื่อหลักฐานไม่ถูกต้อง
            </p>

        </div>

        <a
            href="<?= BC_BASE_PATH ?>/admin/index.php"
            class="admin-button light"
        >

            <i class="fa-solid fa-arrow-left"></i>

            กลับแดชบอร์ด

        </a>

    </div>


    <!-- =====================================
         MESSAGE
    ====================================== -->

    <?php if ($success): ?>

        <div class="payment-alert success">

            <i class="fa-solid fa-circle-check"></i>

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="payment-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         SUMMARY
    ====================================== -->

    <div class="payment-summary">


        <div class="payment-summary-card">

            <span>
                ทั้งหมด
            </span>

            <strong>
                <?= number_format((int)($counts['total_count'] ?? 0)) ?>
            </strong>

        </div>


        <div class="payment-summary-card">

            <span>
                รอตรวจสอบ
            </span>

            <strong>
                <?= number_format((int)($counts['pending_count'] ?? 0)) ?>
            </strong>

        </div>


        <div class="payment-summary-card">

            <span>
                อนุมัติแล้ว
            </span>

            <strong>
                <?= number_format((int)($counts['approved_count'] ?? 0)) ?>
            </strong>

        </div>


        <div class="payment-summary-card">

            <span>
                ไม่อนุมัติ
            </span>

            <strong>
                <?= number_format((int)($counts['rejected_count'] ?? 0)) ?>
            </strong>

        </div>


    </div>


    <!-- =====================================
         FILTER
    ====================================== -->

    <div class="payment-filter">

        <a
            href="index.php"
            class="<?= $status === '' ? 'active' : '' ?>"
        >
            ทั้งหมด
        </a>


        <a
            href="index.php?status=pending"
            class="<?= $status === 'pending' ? 'active' : '' ?>"
        >
            รอตรวจสอบ
        </a>


        <a
            href="index.php?status=approved"
            class="<?= $status === 'approved' ? 'active' : '' ?>"
        >
            อนุมัติแล้ว
        </a>


        <a
            href="index.php?status=rejected"
            class="<?= $status === 'rejected' ? 'active' : '' ?>"
        >
            ไม่อนุมัติ
        </a>

    </div>


    <!-- =====================================
         PAYMENT LIST
    ====================================== -->

    <?php if ($payments): ?>


        <?php foreach ($payments as $payment): ?>

            <?php

            $statusClass =
                $payment['payment_status'];

            $slipPath =
                trim(
                    (string)$payment['payment_slip']
                );

            $slipUrl =
                ('' . BC_BASE_PATH . '/payment_slip_view.php?payment_id=') .
                (int)$payment['id'];

            $extension =
                strtolower(
                    pathinfo(
                        $slipPath,
                        PATHINFO_EXTENSION
                    )
                );

            $isImage =
                in_array(
                    $extension,
                    [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp'
                    ],
                    true
                );

            $canReview =
                $payment['payment_status'] === 'pending'
                &&
                $payment['application_status'] === 'checking_payment';

            ?>


            <div class="payment-card">


                <!-- =================================
                     TOP
                ================================== -->

                <div class="payment-card-top">


                    <div class="payment-person">


                        <div class="payment-person-avatar">

                            <?= htmlspecialchars(
                                mb_substr(
                                    $payment['first_name'],
                                    0,
                                    1
                                )
                            ) ?>

                        </div>


                        <div>

                            <h3>

                                <?= htmlspecialchars(
                                    $payment['first_name'] .
                                    ' ' .
                                    $payment['last_name']
                                ) ?>

                            </h3>

                            <p>

                                <?= htmlspecialchars(
                                    $payment['email']
                                ) ?>

                                ·

                                <?= htmlspecialchars(
                                    $payment['student_id']
                                ) ?>

                            </p>

                        </div>

                    </div>


                    <span
                        class="payment-badge <?= htmlspecialchars($statusClass) ?>"
                    >

                        <?= htmlspecialchars(
                            paymentStatusText(
                                $payment['payment_status']
                            )
                        ) ?>

                    </span>


                </div>


                <!-- =================================
                     BODY
                ================================== -->

                <div class="payment-body">


                    <!-- ข้อมูล -->

                    <div class="payment-information">


                        <div class="payment-info-box">

                            <span>
                                รหัสนิสิต
                            </span>

                            <strong>
                                <?= htmlspecialchars($payment['student_id']) ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                ชั้นปี
                            </span>

                            <strong>
                                ปี <?= (int)$payment['year_level'] ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                คณะ
                            </span>

                            <strong>
                                <?= htmlspecialchars($payment['faculty']) ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                สาขา
                            </span>

                            <strong>
                                <?= htmlspecialchars($payment['major']) ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                ยอดเงิน
                            </span>

                            <strong>
                                <?= number_format(
                                    (float)$payment['amount'],
                                    2
                                ) ?>
                                บาท
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                ช่องทางชำระเงิน
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    paymentMethodText(
                                        $payment['payment_method']
                                    )
                                ) ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                เพศ
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $payment['gender'] ?: '-'
                                ) ?>
                            </strong>

                        </div>


                        <div class="payment-info-box">

                            <span>
                                เบอร์โทรศัพท์
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $payment['phone'] ?: '-'
                                ) ?>
                            </strong>

                        </div>


                    </div>


                    <!-- =================================
                         SLIP
                    ================================== -->

                    <div class="payment-slip">

                        <h4>
                            หลักฐานการชำระเงิน
                        </h4>


                        <?php if ($slipPath !== ''): ?>


                            <?php if ($isImage): ?>

                                <a
                                    href="<?= htmlspecialchars($slipUrl) ?>"
                                    target="_blank"
                                >

                                    <img
                                        src="<?= htmlspecialchars($slipUrl) ?>"
                                        alt="หลักฐานการชำระเงิน"
                                        class="payment-slip-image"
                                    >

                                </a>


                            <?php else: ?>

                                <a
                                    href="<?= htmlspecialchars($slipUrl) ?>"
                                    target="_blank"
                                    class="payment-slip-link"
                                >

                                    <i class="fa-regular fa-file-pdf"></i>

                                    เปิดหลักฐานการชำระเงิน

                                </a>

                            <?php endif; ?>


                        <?php else: ?>

                            <p>
                                ไม่พบไฟล์หลักฐาน
                            </p>

                        <?php endif; ?>


                    </div>


                </div>


                <!-- =================================
                     REVIEW
                ================================== -->

                <?php if ($canReview): ?>


                    <div class="payment-review">


                        <h4>
                            ผลการตรวจสอบ
                        </h4>


                        <!-- =============================
                             APPROVE
                        ============================== -->

                        <div class="payment-actions">


                            <form
                                action="review.php"
                                method="POST"
                                class="approve-form"
                                data-bc-confirm
                                data-bc-confirm-type="approve"
                                data-bc-confirm-title="อนุมัติการชำระเงิน"
                                data-bc-confirm-message="ยืนยันอนุมัติการชำระเงินและรับเป็นสมาชิกหรือไม่?"
                                data-bc-confirm-label="อนุมัติ"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="payment_id"
                                    value="<?= (int)$payment['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="approve"
                                >


                                <button type="submit">

                                    <i class="fa-solid fa-circle-check"></i>

                                    อนุมัติและรับเป็นสมาชิก

                                </button>

                            </form>


                        </div>


                        <!-- =============================
                             REJECT
                        ============================== -->

                        <form
                            action="review.php"
                            method="POST"
                            class="reject-form"
                            data-bc-confirm
                            data-bc-confirm-type="reject"
                            data-bc-confirm-title="ปฏิเสธหลักฐาน"
                            data-bc-confirm-message="ยืนยันการปฏิเสธหลักฐานนี้หรือไม่?"
                            data-bc-confirm-label="ยืนยันการปฏิเสธ"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="payment_id"
                                value="<?= (int)$payment['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="reject"
                            >


                            <div class="reject-grid">


                                <div>

                                    <label>
                                        เหตุผลที่ไม่อนุมัติ *
                                    </label>

                                    <select
                                        name="reject_reason"
                                        required
                                    >

                                        <option value="">
                                            -- เลือกเหตุผล --
                                        </option>

                                        <option value="slip_unclear">
                                            สลิปไม่ชัดเจน
                                        </option>

                                        <option value="wrong_amount">
                                            ยอดเงินไม่ถูกต้อง
                                        </option>

                                        <option value="wrong_slip">
                                            สลิปไม่ตรงกับข้อมูลที่แจ้ง
                                        </option>

                                        <option value="student_info">
                                            ข้อมูลนิสิตไม่ถูกต้อง
                                        </option>

                                        <option value="incomplete">
                                            ข้อมูลไม่ครบถ้วน
                                        </option>

                                        <option value="other">
                                            อื่น ๆ
                                        </option>

                                    </select>

                                </div>


                                <div>

                                    <label>
                                        รายละเอียดเพิ่มเติม
                                    </label>

                                    <textarea
                                        name="reject_detail"
                                        placeholder="เช่น กรุณาส่งสลิปใหม่ที่เห็นยอดเงินและวันที่ชัดเจน"
                                    ></textarea>

                                </div>


                            </div>


                            <button type="submit">

                                <i class="fa-solid fa-circle-xmark"></i>

                                ไม่อนุมัติ

                            </button>


                        </form>


                    </div>


                <?php else: ?>


                    <!-- =================================
                         ตรวจไปแล้ว
                    ================================== -->

                    <?php if ($payment['payment_status'] === 'approved'): ?>

                        <div class="review-result approved">

                            <strong>
                                <i class="fa-solid fa-circle-check"></i>
                                รายการนี้ได้รับการอนุมัติแล้ว
                            </strong>

                            ผู้สมัครได้รับการบันทึกเป็นสมาชิกแล้ว
                            ไม่สามารถปฏิเสธจากหน้าตรวจสลิปได้อีก

                        </div>

                    <?php elseif ($payment['payment_status'] === 'rejected'): ?>

                        <div class="review-result rejected">

                            <strong>
                                <i class="fa-solid fa-circle-xmark"></i>
                                รายการนี้ไม่ผ่านการตรวจสอบ
                            </strong>

                            <?= nl2br(
                                htmlspecialchars(
                                    $payment['payment_admin_note']
                                    ?: $payment['application_admin_note']
                                    ?: 'ไม่ได้ระบุเหตุผล'
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>


                <?php endif; ?>


            </div>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="payment-empty">

            <i class="fa-regular fa-folder-open"></i>

            <h3>
                ไม่พบรายการชำระเงิน
            </h3>

            <p>
                ยังไม่มีรายการตามสถานะที่เลือก
            </p>

        </div>


    <?php endif; ?>


</div>


<?php
include '../includes/confirmation_modal.php';
include '../includes/admin_footer.php';
?>
