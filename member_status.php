<?php
require_once __DIR__ . '/includes/bootstrap.php';
// member_status.php
// หน้านี้ใช้สำหรับแสดงสถานะการสมัครสมาชิกและผลการตรวจสอบ

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


// =========================================
// ต้อง Login
// =========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];


// =========================================
// MESSAGE
// =========================================

$success = $_SESSION['membership_success'] ?? '';
$error = $_SESSION['membership_error'] ?? '';

unset($_SESSION['membership_success']);
unset($_SESSION['membership_error']);


// =========================================
// ตรวจว่าเป็นสมาชิกแล้วหรือยัง
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$member = $stmt->fetch();


// =========================================
// คำขอล่าสุด
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM membership_applications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$userId]);

$application = $stmt->fetch();


// =========================================
// Payment ล่าสุด
// =========================================

$payment = null;

if ($application) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM membership_payments
        WHERE application_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $application['id']
    ]);

    $payment = $stmt->fetch();
}


// =========================================
// ใบเสร็จล่าสุดของผู้ใช้
// =========================================

$receipt = null;

$stmt = $pdo->prepare("
    SELECT
        id,
        receipt_no,
        amount,
        payment_method,
        issued_at
    FROM membership_receipts
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$receipt = $stmt->fetch(PDO::FETCH_ASSOC);


// =========================================
// FUNCTION
// =========================================

function memberPaymentMethodText(?string $method): string
{
    return match ($method) {

        'promptpay' =>
            'QR / พร้อมเพย์',

        'bank_transfer' =>
            'โอนเข้าบัญชีธนาคาร',

        default =>
            '-'
    };
}


function memberPaymentStatusText(?string $status): string
{
    return match ($status) {

        'pending' =>
            'รอตรวจสอบ',

        'approved' =>
            'อนุมัติแล้ว',

        'rejected' =>
            'ไม่อนุมัติ',

        default =>
            '-'
    };
}


$pageTitle = 'สถานะสมาชิก';
$pageCss = 'membership.css';

include 'includes/header.php';
?>


<style>

/* =========================================
   STATUS EXTRA
========================================= */

.status-reject-note{
    max-width:680px;
    margin:20px auto 0;
    padding:17px 19px;

    border:1px solid #efd1d1;
    border-radius:11px;

    background:#fff6f6;

    color:#884747;

    text-align:left;
}

.status-reject-note strong{
    margin-bottom:7px;

    display:block;

    color:#a13f3f;

    font-size:11px;
}

.status-reject-note p{
    margin:0 !important;

    color:#79545a !important;

    font-size:10px;
    line-height:1.8;
}

.status-help-text{
    max-width:650px;

    margin:14px auto 0 !important;

    color:#7d8d9d !important;

    font-size:9px !important;
    line-height:1.8;
}

.payment-status-card{
    margin-top:20px;
}

.payment-status-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
}

.payment-status-grid>div{
    padding:13px;

    border:1px solid #e4eaf0;
    border-radius:9px;

    background:#fafcff;
}

.payment-status-grid span{
    margin-bottom:3px;

    display:block;

    color:#8b99a8;

    font-size:8px;
}

.payment-status-grid strong{
    color:#425f7b;

    font-size:10px;
    font-weight:500;
}


.receipt-action{
    margin-top:18px;
    display:flex;
    justify-content:center;
}

.receipt-view-btn{
    min-height:42px;
    padding:10px 18px;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;

    color:#ffffff;
    background:#1458bd;

    border:1px solid #1458bd;
    border-radius:9px;

    font-size:11px;
    font-weight:600;
    text-decoration:none;

    transition:.18s ease;
}

.receipt-view-btn:hover{
    background:#0f49a0;
    border-color:#0f49a0;
}

@media(max-width:700px){

    .payment-status-grid{
        grid-template-columns:1fr;
    }

}

</style>


<section class="membership-page">

    <div class="membership-container status-container">


        <!-- =====================================
             HEADER
        ====================================== -->

        <div class="status-header">

            <span>
                MEMBER STATUS
            </span>

            <h1>
                สถานะสมาชิกของฉัน
            </h1>

            <p>
                ตรวจสอบสถานะการสมัครและการเป็นสมาชิก
                Bizcomp Club
            </p>

        </div>


        <!-- =====================================
             MESSAGE
        ====================================== -->

        <?php if ($success): ?>

            <div class="membership-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="membership-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================
             เป็นสมาชิกแล้ว
        ====================================== -->

        <?php if ($member): ?>

            <?php

            $automaticStatus =
                getAutomaticMemberStatus($member);

            $admissionYear =
                getMemberAdmissionYear($member);

            ?>


            <div class="status-card active-member">

                <div class="status-main-icon">

                    <i class="fa-solid fa-user-check"></i>

                </div>

                <span class="status-label success">
                    สมาชิกชมรม
                </span>

                <h2>
                    คุณเป็นสมาชิก Bizcomp Club แล้ว
                </h2>


                <?php if (
                    $automaticStatus['code'] === 'active'
                    &&
                    $automaticStatus['year_level'] !== null
                ): ?>

                    <p>

                        ชั้นปีปัจจุบัน:

                        <strong>
                            ปี <?= (int)$automaticStatus['year_level'] ?>
                        </strong>

                    </p>

                <?php else: ?>

                    <p>

                        <?= htmlspecialchars(
                            $automaticStatus['text']
                        ) ?>

                    </p>

                <?php endif; ?>


                <div class="status-information">

                    <div>

                        <span>
                            รหัสนิสิต
                        </span>

                        <strong>
                            <?= htmlspecialchars($member['student_id']) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            ปีที่เข้า
                        </span>

                        <strong>

                            <?= $admissionYear
                                ? (int)$admissionYear
                                : '-'
                            ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            สาขา
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $member['major'] ?: '-'
                            ) ?>

                        </strong>

                    </div>

                </div>


                <?php if ($receipt): ?>

                    <div class="receipt-action">

                        <a
                            href="receipt.php?id=<?= (int)$receipt['id'] ?>"
                            class="receipt-view-btn"
                        >

                            <i class="fa-regular fa-eye"></i>

                            ดูใบเสร็จ

                        </a>

                    </div>

                <?php endif; ?>


            </div>


        <!-- =====================================
             ยังไม่เคยสมัคร
        ====================================== -->

        <?php elseif (!$application): ?>


            <div class="status-card">

                <div class="status-main-icon neutral">

                    <i class="fa-regular fa-id-card"></i>

                </div>

                <span class="status-label">
                    ยังไม่ได้สมัคร
                </span>

                <h2>
                    คุณยังไม่ได้เป็นสมาชิกชมรม
                </h2>

                <p>
                    สามารถสมัครสมาชิก Bizcomp Club
                    ด้วยบัญชีนี้ได้
                </p>


                <a
                    href="membership_apply.php"
                    class="membership-primary-btn status-button"
                >

                    <i class="fa-solid fa-user-plus"></i>

                    สมัครสมาชิกชมรม

                </a>

            </div>


        <!-- =====================================
             มีคำขอ
        ====================================== -->

        <?php else: ?>


            <!-- =================================
                 PENDING
            ================================== -->

            <?php if ($application['application_status'] === 'pending'): ?>


                <div class="status-card">

                    <div class="status-main-icon waiting">

                        <i class="fa-solid fa-clock"></i>

                    </div>

                    <span class="status-label waiting">
                        รอตรวจสอบ
                    </span>

                    <h2>
                        ส่งคำขอสมัครสมาชิกแล้ว
                    </h2>

                    <p>
                        ขณะนี้คำขอของคุณกำลังรอผู้ดูแลระบบตรวจสอบ
                    </p>

                </div>


            <!-- =================================
                 WAITING PAYMENT
            ================================== -->

            <?php elseif (
                $application['application_status'] === 'waiting_payment'
            ): ?>


                <div class="status-card">

                    <div class="status-main-icon payment">

                        <i class="fa-regular fa-credit-card"></i>

                    </div>

                    <span class="status-label payment">
                        รอชำระเงิน
                    </span>

                    <h2>
                        กรุณาส่งหลักฐานการชำระเงิน
                    </h2>

                    <p>
                        ค่าสมัครสมาชิกจำนวน
                        <strong>20.00 บาท</strong>
                    </p>


                    <a
                        href="membership_payment.php"
                        class="membership-primary-btn status-button"
                    >

                        <i class="fa-solid fa-receipt"></i>

                        ชำระเงินและส่งหลักฐาน

                    </a>

                </div>


            <!-- =================================
                 CHECKING PAYMENT
            ================================== -->

            <?php elseif (
                $application['application_status'] === 'checking_payment'
            ): ?>


                <div class="status-card">

                    <div class="status-main-icon checking">

                        <i class="fa-solid fa-magnifying-glass"></i>

                    </div>

                    <span class="status-label checking">
                        รอตรวจสอบการชำระเงิน
                    </span>

                    <h2>
                        ส่งข้อมูลและหลักฐานเรียบร้อยแล้ว
                    </h2>

                    <p>
                        ผู้ดูแลระบบกำลังตรวจสอบข้อมูล
                        และหลักฐานการชำระเงินของคุณ
                    </p>

                    <p class="status-help-text">
                        เมื่อผู้ดูแลระบบตรวจสอบเรียบร้อยแล้ว
                        สถานะในหน้านี้จะเปลี่ยนโดยอัตโนมัติ
                    </p>

                </div>


            <!-- =================================
                 APPROVED
            ================================== -->

            <?php elseif (
                $application['application_status'] === 'approved'
            ): ?>


                <div class="status-card">

                    <div class="status-main-icon success">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>

                    <span class="status-label success">
                        อนุมัติแล้ว
                    </span>

                    <h2>
                        การสมัครสมาชิกได้รับการอนุมัติ
                    </h2>

                    <p>
                        การสมัครสมาชิกของคุณผ่านการตรวจสอบเรียบร้อยแล้ว
                    </p>

                </div>


            <!-- =================================
                 REJECTED
            ================================== -->

            <?php elseif (
                $application['application_status'] === 'rejected'
            ): ?>


                <div class="status-card">

                    <div class="status-main-icon rejected">

                        <i class="fa-solid fa-circle-xmark"></i>

                    </div>

                    <span class="status-label rejected">
                        ไม่ผ่านการตรวจสอบ
                    </span>

                    <h2>
                        คำขอของคุณต้องได้รับการแก้ไข
                    </h2>

                    <p>
                        บัญชีของคุณยังสามารถใช้งานเว็บไซต์ได้ตามปกติ
                        แต่ยังไม่ได้รับสิทธิ์เป็นสมาชิกชมรม
                    </p>


                    <!-- เหตุผล -->

                    <div class="status-reject-note">

                        <strong>

                            <i class="fa-solid fa-circle-exclamation"></i>

                            เหตุผลจากผู้ดูแลระบบ

                        </strong>

                        <p>

                            <?php if (!empty($application['admin_note'])): ?>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $application['admin_note']
                                    )
                                ) ?>

                            <?php else: ?>

                                ไม่ได้ระบุเหตุผลเพิ่มเติม
                                กรุณาตรวจสอบข้อมูลและส่งใหม่อีกครั้ง

                            <?php endif; ?>

                        </p>

                    </div>


                    <p class="status-help-text">

                        ไม่จำเป็นต้องสร้างบัญชีใหม่
                        สามารถแก้ไขข้อมูลหรือแนบสลิปใหม่
                        แล้วส่งให้ผู้ดูแลระบบตรวจสอบอีกครั้งได้

                    </p>


                    <a
                        href="membership_payment.php"
                        class="membership-primary-btn status-button"
                    >

                        <i class="fa-solid fa-pen-to-square"></i>

                        แก้ไขและส่งตรวจใหม่

                    </a>

                </div>


            <?php endif; ?>


            <!-- =================================
                 APPLICATION DETAIL
            ================================== -->

            <div class="application-detail-card">

                <div class="section-title">

                    <i class="fa-regular fa-file-lines"></i>

                    <div>

                        <h3>
                            ข้อมูลคำขอสมัครสมาชิก
                        </h3>

                        <p>
                            ข้อมูลล่าสุดที่ส่งให้ผู้ดูแลระบบ
                        </p>

                    </div>

                </div>


                <div class="application-detail-grid">


                    <div>

                        <span>
                            รหัสนิสิต
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application['student_id']
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            ชั้นปี
                        </span>

                        <strong>
                            ปี <?= (int)$application['year_level'] ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            คณะ
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application['faculty']
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            สาขา
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application['major']
                            ) ?>

                        </strong>

                    </div>


                </div>

            </div>


            <!-- =================================
                 PAYMENT DETAIL
            ================================== -->

            <?php if ($payment): ?>

                <div class="application-detail-card payment-status-card">

                    <div class="section-title">

                        <i class="fa-solid fa-receipt"></i>

                        <div>

                            <h3>
                                ข้อมูลการชำระเงินล่าสุด
                            </h3>

                            <p>
                                ผลการตรวจสอบหลักฐานล่าสุด
                            </p>

                        </div>

                    </div>


                    <div class="payment-status-grid">


                        <div>

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


                        <div>

                            <span>
                                ช่องทาง
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    memberPaymentMethodText(
                                        $payment['payment_method']
                                    )
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                สถานะ
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    memberPaymentStatusText(
                                        $payment['payment_status']
                                    )
                                ) ?>

                            </strong>

                        </div>


                    </div>

                </div>

            <?php endif; ?>


        <?php endif; ?>


    </div>

</section>


<?php
include 'includes/footer.php';
?>
