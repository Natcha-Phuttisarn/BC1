<?php
require_once __DIR__ . '/includes/bootstrap.php';
// membership_payment.php
// หน้านี้ใช้สำหรับแก้ไขข้อมูลและส่งหลักฐานการชำระเงินใหม่

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
// ถ้าเป็นสมาชิกแล้ว
// =========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

if ($stmt->fetch()) {

    header('Location: member_status.php');
    exit;
}


// =========================================
// คำขอที่สามารถส่งใหม่ได้
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM membership_applications
    WHERE user_id = ?
    AND application_status IN (
        'waiting_payment',
        'rejected'
    )
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$userId]);

$application = $stmt->fetch();


if (!$application) {

    header('Location: member_status.php');
    exit;
}


// =========================================
// Payment ล่าสุด
// =========================================

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

$lastPayment = $stmt->fetch();


// =========================================
// MODE
// =========================================

$isRetry =
    $application['application_status']
    === 'rejected';


// =========================================
// ERROR / OLD
// =========================================

$error =
    $_SESSION['payment_submit_error']
    ?? '';

$old =
    $_SESSION['payment_retry_old']
    ?? [];

unset($_SESSION['payment_submit_error']);
unset($_SESSION['payment_retry_old']);


// =========================================
// ค่าสมัครสมาชิก
// =========================================

$membershipFee = 20.00;


// =========================================
// PAYMENT SETTINGS
// =========================================

$paymentSettings = [

    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => ''

];

try {

    $stmt = $pdo->query("
        SELECT setting_key, setting_value
        FROM site_settings
        WHERE setting_key IN (
            'bank_name',
            'bank_account_name',
            'bank_account_number',
            'promptpay_number'
        )
    ");

    foreach ($stmt->fetchAll() as $row) {

        if (
            array_key_exists(
                $row['setting_key'],
                $paymentSettings
            )
        ) {

            $paymentSettings[
                $row['setting_key']
            ] = $row['setting_value'];
        }
    }

} catch (Throwable $e) {
    // ใช้ค่าเริ่มต้น
}


// =========================================
// QR
// =========================================

$qrPath =
    'assets/images/payment-qr.png';

$qrFile =
    __DIR__ .
    '/' .
    $qrPath;

$qrExists =
    is_file($qrFile)
    &&
    filesize($qrFile) > 0;


// =========================================
// CSRF
// =========================================

if (empty($_SESSION['payment_retry_csrf'])) {

    $_SESSION['payment_retry_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['payment_retry_csrf'];


// =========================================
// FORM VALUE
// =========================================

$studentId =
    $old['student_id']
    ?? $application['student_id'];

$faculty =
    $old['faculty']
    ?? $application['faculty'];

$major =
    $old['major']
    ?? $application['major'];

$gender =
    $old['gender']
    ?? $application['gender'];

$phone =
    $old['phone']
    ?? $application['phone'];

$paymentMethod =
    $old['payment_method']
    ?? ($lastPayment['payment_method'] ?? '');


// =========================================
// REJECT NOTE
// =========================================

$rejectNote =
    $application['admin_note']
    ?? '';

if (
    $rejectNote === ''
    &&
    $lastPayment
) {

    $rejectNote =
        $lastPayment['admin_note']
        ?? '';
}


$pageTitle =
    $isRetry
    ? 'แก้ไขและส่งตรวจใหม่'
    : 'ชำระค่าสมาชิก';

$pageCss = 'membership.css';

include 'includes/header.php';
?>


<style>

.retry-note{
    margin-bottom:20px;
    padding:16px 18px;

    border:1px solid #efd0d0;
    border-radius:11px;

    background:#fff5f5;
    color:#854747;
}

.retry-note strong{
    margin-bottom:6px;
    display:block;
    font-size:11px;
}

.retry-note p{
    margin:0;
    font-size:9px;
    line-height:1.8;
}

.retry-section{
    margin-bottom:22px;
    padding:20px;

    border:1px solid #e2e8ef;
    border-radius:12px;

    background:#fff;
}

.retry-section h3{
    margin:0 0 5px;

    color:#405f7d;

    font-size:13px;
}

.retry-section>p{
    margin:0 0 17px;

    color:#8b99a8;

    font-size:9px;
}

.retry-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.retry-grid .membership-form-group.full{
    grid-column:1/-1;
}

.payment-account-box{
    margin-bottom:18px;
    padding:17px;

    border:1px solid #dce7f2;
    border-radius:11px;

    background:#f7faff;
}

.payment-account-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:10px;
}

.payment-account-grid div{
    padding:10px;

    border:1px solid #e2e9f0;
    border-radius:8px;

    background:#fff;
}

.payment-account-grid span{
    display:block;

    color:#8a98a5;

    font-size:8px;
}

.payment-account-grid strong{
    display:block;

    margin-top:3px;

    color:#405d79;

    font-size:10px;
}

.payment-qr-retry{
    margin-bottom:18px;

    text-align:center;
}

.payment-qr-retry img{
    width:190px;
    max-width:100%;

    padding:10px;

    border:1px solid #e1e7ed;
    border-radius:12px;

    background:#fff;
}

.old-slip-box{
    margin:14px 0;
    padding:13px;

    border:1px solid #e4eaf0;
    border-radius:9px;

    background:#fafcff;

    color:#667c91;

    font-size:9px;
}

.old-slip-box a{
    color:#1768cf;
    font-weight:500;
    text-decoration:none;
}

.retry-help{
    margin-top:8px;

    color:#8997a5;

    font-size:8px;
    line-height:1.7;
}

@media(max-width:700px){

    .retry-grid,
    .payment-account-grid{
        grid-template-columns:1fr;
    }

    .retry-grid .membership-form-group.full{
        grid-column:auto;
    }

}

</style>


<section class="membership-page">

    <div class="membership-container payment-container">

        <div class="membership-card">


            <!-- =====================================
                 HEADING
            ====================================== -->

            <div class="membership-heading">

                <div class="membership-heading-icon">

                    <i class="fa-solid fa-file-pen"></i>

                </div>

                <div>

                    <span>
                        MEMBERSHIP REVIEW
                    </span>

                    <h1>

                        <?= $isRetry
                            ? 'แก้ไขและส่งตรวจใหม่'
                            : 'ชำระค่าสมาชิก'
                        ?>

                    </h1>

                    <p>

                        <?= $isRetry
                            ? 'ตรวจสอบข้อมูลและส่งให้ผู้ดูแลระบบพิจารณาอีกครั้ง'
                            : 'ส่งหลักฐานการชำระเงินเพื่อให้ผู้ดูแลระบบตรวจสอบ'
                        ?>

                    </p>

                </div>

            </div>


            <!-- =====================================
                 REJECTION
            ====================================== -->

            <?php if ($isRetry): ?>

                <div class="retry-note">

                    <strong>

                        <i class="fa-solid fa-circle-exclamation"></i>

                        เหตุผลที่ไม่ผ่านการตรวจสอบ

                    </strong>

                    <p>

                        <?= $rejectNote !== ''
                            ? nl2br(
                                htmlspecialchars(
                                    $rejectNote
                                )
                            )
                            : 'กรุณาตรวจสอบข้อมูลและหลักฐานก่อนส่งใหม่'
                        ?>

                    </p>

                </div>

            <?php endif; ?>


            <!-- =====================================
                 ERROR
            ====================================== -->

            <?php if ($error): ?>

                <div class="membership-alert error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- =====================================
                 PRICE
            ====================================== -->

            <div class="payment-amount-card">

                <span>
                    ค่าสมัครสมาชิก
                </span>

                <strong>

                    <?= number_format(
                        $membershipFee,
                        2
                    ) ?>

                    บาท

                </strong>

                <p>
                    ค่าสมัครสมาชิกกำหนดตายตัว
                    <?= number_format($membershipFee, 2) ?> บาท
                </p>

            </div>


            <!-- =====================================
                 PAYMENT ACCOUNT
            ====================================== -->

            <?php if ($qrExists): ?>

                <div class="payment-qr-retry">

                    <img
                        src="<?= htmlspecialchars($qrPath) ?>"
                        alt="QR Code ชำระเงิน"
                    >

                </div>

            <?php endif; ?>


            <div class="payment-account-box">

                <div class="payment-account-grid">


                    <div>

                        <span>
                            ธนาคาร
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $paymentSettings['bank_name']
                                ?: '-'
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            ชื่อบัญชี
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $paymentSettings['bank_account_name']
                                ?: '-'
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            เลขบัญชี
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $paymentSettings['bank_account_number']
                                ?: '-'
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            พร้อมเพย์
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $paymentSettings['promptpay_number']
                                ?: '-'
                            ) ?>

                        </strong>

                    </div>


                </div>

            </div>


            <!-- =====================================
                 FORM
            ====================================== -->

            <form
                action="membership_payment_save.php"
                method="POST"
                enctype="multipart/form-data"
                class="membership-form"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="application_id"
                    value="<?= (int)$application['id'] ?>"
                >


                <!-- =================================
                     MEMBER DATA
                ================================== -->

                <div class="retry-section">

                    <h3>
                        ข้อมูลสมาชิก
                    </h3>

                    <p>
                        สามารถแก้ไขข้อมูลที่ไม่ถูกต้องก่อนส่งตรวจใหม่
                    </p>


                    <div class="retry-grid">


                        <div class="membership-form-group">

                            <label>
                                รหัสนิสิต
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="student_id"
                                value="<?= htmlspecialchars($studentId) ?>"
                                maxlength="30"
                                required
                            >

                        </div>


                        <div class="membership-form-group">

                            <label>
                                เบอร์โทรศัพท์
                            </label>

                            <input
                                type="text"
                                name="phone"
                                value="<?= htmlspecialchars($phone ?? '') ?>"
                                maxlength="30"
                            >

                        </div>


                        <div class="membership-form-group">

                            <label>
                                คณะ
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="faculty"
                                value="<?= htmlspecialchars($faculty) ?>"
                                required
                            >

                        </div>


                        <div class="membership-form-group">

                            <label>
                                สาขา
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="major"
                                value="<?= htmlspecialchars($major) ?>"
                                required
                            >

                        </div>


                        <div class="membership-form-group full">

                            <label>
                                เพศ
                            </label>

                            <select name="gender">

                                <option value="">
                                    ไม่ระบุ
                                </option>

                                <option
                                    value="ชาย"
                                    <?= $gender === 'ชาย'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    ชาย
                                </option>

                                <option
                                    value="หญิง"
                                    <?= $gender === 'หญิง'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    หญิง
                                </option>

                                <option
                                    value="อื่นๆ"
                                    <?= $gender === 'อื่นๆ'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    อื่น ๆ
                                </option>

                            </select>

                        </div>


                    </div>

                </div>


                <!-- =================================
                     PAYMENT
                ================================== -->

                <div class="retry-section">

                    <h3>
                        หลักฐานการชำระเงิน
                    </h3>

                    <p>
                        ตรวจสอบช่องทางและหลักฐานก่อนส่งให้ผู้ดูแลระบบ
                    </p>


                    <div class="membership-form-group">

                        <label>

                            ช่องทางการชำระเงิน

                            <span>*</span>

                        </label>

                        <select
                            name="payment_method"
                            required
                        >

                            <option value="">
                                เลือกช่องทาง
                            </option>

                            <option
                                value="promptpay"
                                <?= $paymentMethod === 'promptpay'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                QR / พร้อมเพย์
                            </option>

                            <option
                                value="bank_transfer"
                                <?= $paymentMethod === 'bank_transfer'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                โอนเข้าบัญชีธนาคาร
                            </option>

                        </select>

                    </div>


                    <?php if (
                        $isRetry
                        &&
                        !empty($lastPayment['payment_slip'])
                    ): ?>

                        <div class="old-slip-box">

                            <i class="fa-solid fa-receipt"></i>

                            มีหลักฐานการชำระเงินเดิมอยู่แล้ว

                            <a
                                href="payment_slip_view.php?payment_id=<?= (int)$lastPayment['id'] ?>"
                                target="_blank"
                            >
                                ดูสลิปเดิม
                            </a>

                        </div>

                    <?php endif; ?>


                    <div class="membership-form-group">

                        <label>

                            <?= $isRetry
                                ? 'หลักฐานการชำระเงินใหม่'
                                : 'หลักฐานการชำระเงิน'
                            ?>

                            <?php if (!$isRetry): ?>
                                <span>*</span>
                            <?php endif; ?>

                        </label>


                        <label class="payment-upload">

                            <i class="fa-solid fa-cloud-arrow-up"></i>

                            <strong>
                                เลือกรูปสลิปหรือ PDF
                            </strong>

                            <span>
                                JPG, PNG, WEBP หรือ PDF
                                ขนาดไม่เกิน 5 MB
                            </span>


                            <input
                                type="file"
                                name="payment_slip"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                <?= !$isRetry
                                    ? 'required'
                                    : ''
                                ?>
                            >

                        </label>


                        <?php if ($isRetry): ?>

                            <p class="retry-help">

                                หากผู้ดูแลแจ้งว่าปัญหาเกิดจาก
                                “ข้อมูลนิสิต”
                                และสลิปเดิมถูกต้อง
                                สามารถไม่เลือกสลิปใหม่ได้
                                ระบบจะใช้สลิปเดิมส่งตรวจอีกครั้ง

                                <br>

                                หากเหตุผลเกี่ยวกับสลิป
                                ยอดเงิน หรือหลักฐานไม่ถูกต้อง
                                กรุณาแนบสลิปใหม่

                            </p>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- =================================
                     ACTION
                ================================== -->

                <div class="membership-actions">

                    <a
                        href="member_status.php"
                        class="membership-secondary-btn"
                    >
                        กลับ
                    </a>


                    <button
                        type="submit"
                        class="membership-primary-btn"
                    >

                        <i class="fa-regular fa-paper-plane"></i>

                        <?= $isRetry
                            ? 'ส่งตรวจใหม่'
                            : 'ส่งหลักฐานการชำระเงิน'
                        ?>

                    </button>

                </div>


            </form>

        </div>

    </div>

</section>


<?php
include 'includes/footer.php';
?>
