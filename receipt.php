<?php
require_once __DIR__ . '/includes/bootstrap.php';
// receipt.php
// หน้านี้ใช้สำหรับแสดงและพิมพ์ใบเสร็จรับเงินของสมาชิก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$receiptId = (int)($_GET['id'] ?? 0);

if ($receiptId <= 0) {
    header('Location: member_status.php');
    exit;
}


/* =========================================
   ดึงข้อมูลใบเสร็จ
========================================= */

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.receipt_no,
        r.amount,
        r.payment_method,
        r.issued_at,

        ma.student_id,
        ma.faculty,
        ma.major,
        ma.year_level,

        u.first_name,
        u.last_name,
        u.email,

        verifier.first_name AS verifier_first_name,
        verifier.last_name AS verifier_last_name

    FROM membership_receipts r

    INNER JOIN membership_applications ma
        ON ma.id = r.application_id

    INNER JOIN users u
        ON u.id = r.user_id

    LEFT JOIN membership_payments mp
        ON mp.id = r.payment_id

    LEFT JOIN users verifier
        ON verifier.id = mp.verified_by

    WHERE r.id = ?
      AND r.user_id = ?

    LIMIT 1
");

$stmt->execute([
    $receiptId,
    $userId
]);

$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    header('Location: member_status.php');
    exit;
}


/* =========================================
   FUNCTIONS
========================================= */

function receiptE($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function receiptPaymentMethod(?string $method): string
{
    return match ($method) {

        'promptpay' =>
            'QR / พร้อมเพย์',

        'bank_transfer' =>
            'โอนเข้าบัญชีธนาคาร',

        default =>
            $method ?: '-'
    };
}


function receiptThaiDate(?string $date): string
{
    if (!$date) {
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
    $month = $months[(int)date('m', $timestamp)];
    $year = (int)date('Y', $timestamp) + 543;
    $time = date('H:i', $timestamp);

    return
        $day . ' ' .
        $month . ' ' .
        $year . ' เวลา ' .
        $time . ' น.';
}


$pageTitle = 'ใบเสร็จรับเงิน';

include 'includes/header.php';
?>

<link
    rel="stylesheet"
    href="assets/css/receipt.css?v=1"
>


<section class="receipt-page">

    <div class="receipt-container">


        <!-- ปุ่มด้านบน -->

        <div class="receipt-top-actions no-print">

            <a
                href="member_status.php"
                class="receipt-back-btn"
            >
                <i class="fa-solid fa-arrow-left"></i>
                กลับหน้าสถานะสมาชิก
            </a>

        </div>


        <!-- ใบเสร็จ -->

        <div class="receipt-paper">


            <!-- HEADER -->

            <div class="receipt-header">

                <div class="receipt-brand">

                    <img
                        src="assets/images/logo.png"
                        alt="Bizcomp Club"
                    >

                    <div>

                        <strong>
                            ชมรมบิตคอม
                        </strong>

                        <small>
                            คณะการบัญชีและการจัดการ<br>
                            มหาวิทยาลัยมหาสารคาม
                        </small>

                    </div>

                </div>


                <div class="receipt-title">

                    <h1>
                        ใบเสร็จรับเงิน
                    </h1>

                    <span>
                        RECEIPT
                    </span>

                </div>

            </div>


            <div class="receipt-line"></div>


            <!-- เลขใบเสร็จ -->

            <div class="receipt-meta">

                <div>

                    <span>
                        เลขที่ใบเสร็จ
                    </span>

                    <strong>
                        <?= receiptE(
                            $receipt['receipt_no']
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        วันที่ออกใบเสร็จ
                    </span>

                    <strong>
                        <?= receiptE(
                            receiptThaiDate(
                                $receipt['issued_at']
                            )
                        ) ?>
                    </strong>

                </div>

            </div>


            <!-- ข้อมูลผู้ชำระ -->

            <div class="receipt-section">

                <h2>
                    ข้อมูลผู้ชำระเงิน
                </h2>


                <div class="receipt-info-grid">

                    <div>

                        <span>
                            ชื่อ - นามสกุล
                        </span>

                        <strong>
                            <?= receiptE(
                                $receipt['first_name'] .
                                ' ' .
                                $receipt['last_name']
                            ) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            รหัสนิสิต
                        </span>

                        <strong>
                            <?= receiptE(
                                $receipt['student_id']
                            ) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            อีเมล
                        </span>

                        <strong>
                            <?= receiptE(
                                $receipt['email']
                            ) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            ชั้นปี
                        </span>

                        <strong>
                            ปี <?= (int)$receipt['year_level'] ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            คณะ
                        </span>

                        <strong>
                            <?= receiptE(
                                $receipt['faculty'] ?: '-'
                            ) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            สาขา
                        </span>

                        <strong>
                            <?= receiptE(
                                $receipt['major'] ?: '-'
                            ) ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- รายการ -->

            <div class="receipt-section">

                <h2>
                    รายการชำระเงิน
                </h2>


                <div class="receipt-table">

                    <div class="receipt-table-head">

                        <span>
                            รายการ
                        </span>

                        <span>
                            จำนวนเงิน
                        </span>

                    </div>


                    <div class="receipt-table-row">

                        <span>
                            ค่าบำรุงสมาชิกชมรม
                        </span>

                        <strong>
                            <?= number_format(
                                (float)$receipt['amount'],
                                2
                            ) ?>
                            บาท
                        </strong>

                    </div>


                    <div class="receipt-total">

                        <span>
                            รวมทั้งสิ้น
                        </span>

                        <strong>
                            <?= number_format(
                                (float)$receipt['amount'],
                                2
                            ) ?>
                            บาท
                        </strong>

                    </div>

                    <div class="receipt-amount-words">
                        (<?= receiptE(thaiBahtText($receipt['amount'])) ?>)
                    </div>

                </div>

            </div>


            <!-- PAYMENT -->

            <div class="receipt-payment">

                <div>

                    <span>
                        ช่องทางการชำระเงิน
                    </span>

                    <strong>
                        <?= receiptE(
                            receiptPaymentMethod(
                                $receipt['payment_method']
                            )
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        สถานะการชำระเงิน
                    </span>

                    <strong class="paid-status">

                        <i class="fa-solid fa-circle-check"></i>

                        ชำระเงินเรียบร้อยแล้ว

                    </strong>

                </div>

            </div>


            <!-- ผู้รับเงิน / ผู้ตรวจสอบ -->

            <div class="receipt-signatures">

                <div>
                    <span class="receipt-signature-line"></span>
                    <strong>ผู้รับเงิน</strong>
                    <small>วันที่ ........../........../..........</small>
                </div>

                <div>
                    <span class="receipt-signature-line"></span>
                    <strong>ผู้ตรวจสอบ</strong>
                    <small>
                        <?php
                        $verifierName = trim(
                            ($receipt['verifier_first_name'] ?? '') . ' ' .
                            ($receipt['verifier_last_name'] ?? '')
                        );
                        ?>
                        <?= receiptE($verifierName !== '' ? $verifierName : '................................') ?>
                    </small>
                </div>

            </div>


            <!-- FOOTER -->

            <div class="receipt-footer">

                <p>
                    ขอบคุณที่สมัครสมาชิก
                </p>

                <strong>
                    ชมรมบิตคอม
                </strong>

                <span>
                    คณะการบัญชีและการจัดการ
                    มหาวิทยาลัยมหาสารคาม
                </span>

            </div>

        </div>


        <!-- ACTION -->

        <div class="receipt-actions no-print">

            <button
                type="button"
                onclick="window.print()"
                class="receipt-print-btn"
            >
                <i class="fa-solid fa-print"></i>

                พิมพ์ใบเสร็จ
            </button>


            <a
                href="member_status.php"
                class="receipt-secondary-btn"
            >
                กลับหน้าสถานะสมาชิก
            </a>

        </div>

    </div>

</section>


<?php
include 'includes/footer.php';
?>
