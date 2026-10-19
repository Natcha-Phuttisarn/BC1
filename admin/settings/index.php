<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// index.php
// หน้านี้ใช้สำหรับตั้งค่าข้อมูลเว็บไซต์และข้อมูลรับชำระค่าสมัครสมาชิก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/db.php';
require_once '../includes/admin_auth.php';

$adminPageTitle = 'ตั้งค่าเว็บไซต์';
$pageTitle = 'ตั้งค่าเว็บไซต์';


// =========================================
// ค่าเริ่มต้น
// =========================================

$settings = [
    'club_name' => 'Bizcomp Club',
    'address' => 'คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม',
    'phone' => '',
    'email' => '',
    'facebook' => 'https://www.facebook.com/profile.php?id=100094575348518',
    'tiktok' => 'https://www.tiktok.com/@bizcompclub',
    'google_maps_url' => 'https://maps.app.goo.gl/yoUfi96Wze3pY2A38',

    // การรับชำระเงิน
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => '',
    'membership_fee' => '20.00'
];


// =========================================
// ดึงข้อมูลจาก site_settings
// =========================================

try {

    $stmt = $pdo->query("
        SELECT setting_key, setting_value
        FROM site_settings
    ");

    foreach ($stmt->fetchAll() as $row) {

        $key = $row['setting_key'];

        if (array_key_exists($key, $settings)) {
            $settings[$key] = $row['setting_value'];
        }
    }
} catch (Throwable $e) {

    // หากอ่านฐานข้อมูลไม่ได้
    // ใช้ค่าเริ่มต้นด้านบน
}


// =========================================
// QR CODE
// =========================================

$qrFile =
    __DIR__ .
    '/../../assets/images/payment-qr.png';

$qrUrl =
    ('' . BC_BASE_PATH . '/assets/images/payment-qr.png');

$qrExists =
    is_file($qrFile);


// =========================================
// MESSAGE
// =========================================

$success =
    $_SESSION['settings_success']
    ?? '';

$error =
    $_SESSION['settings_error']
    ?? '';

unset($_SESSION['settings_success']);
unset($_SESSION['settings_error']);


// =========================================
// CSRF
// =========================================

if (empty($_SESSION['settings_csrf'])) {

    $_SESSION['settings_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['settings_csrf'];


// =========================================
// ADMIN LAYOUT
// =========================================

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>


<style>
    /* =========================================
   SETTINGS PAGE
========================================= */

    .settings-page {
        width: 100%;
        max-width: 1050px;
        margin: 0 auto;
        padding: 10px 0 35px;
    }


    /* =========================================
   TOP HEADING
========================================= */

    .settings-top {
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .settings-heading {
        margin: 0;
    }

    .settings-heading>span {
        color: #1768cf;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 1.3px;
    }

    .settings-heading h2 {
        margin: 4px 0;
        color: #24496e;
        font-size: 27px;
        font-weight: 600;
    }

    .settings-heading p {
        margin: 0;
        color: #8797a8;
        font-size: 11px;
    }


    /* =========================================
   BACK DASHBOARD BUTTON
========================================= */

    .back-dashboard-btn {
        min-height: 41px;
        padding: 0 16px;

        border: 1px solid #d7e0e9;
        border-radius: 9px;

        background: #fff;
        color: #58718a;

        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;

        font-family: "Prompt", sans-serif;
        font-size: 10px;
        font-weight: 500;

        text-decoration: none;
        white-space: nowrap;

        transition: .2s;
    }

    .back-dashboard-btn:hover {
        border-color: #1768cf;
        background: #f4f8fd;
        color: #1768cf;
    }


    /* =========================================
   ALERT
========================================= */

    .settings-alert {
        margin-bottom: 18px;
        padding: 13px 16px;

        border-radius: 10px;

        display: flex;
        align-items: center;
        gap: 9px;

        font-size: 11px;
    }

    .settings-alert.success {
        color: #237449;
        background: #edf9f2;
        border: 1px solid #caead8;
    }

    .settings-alert.error {
        color: #b33f3f;
        background: #fff1f1;
        border: 1px solid #efcece;
    }


    /* =========================================
   CARD
========================================= */

    .settings-card {
        margin-bottom: 20px;
        padding: 25px;

        background: #fff;

        border: 1px solid #e0e7ef;
        border-radius: 15px;

        box-shadow:
            0 6px 22px rgba(25, 60, 100, .045);
    }

    .settings-card-heading {
        margin-bottom: 21px;
        padding-bottom: 16px;

        border-bottom: 1px solid #e8edf3;

        display: flex;
        align-items: center;
        gap: 12px;
    }

    .settings-icon {
        width: 40px;
        height: 40px;

        flex-shrink: 0;

        border-radius: 10px;

        background: #eaf3ff;
        color: #1768cf;

        display: flex;
        align-items: center;
        justify-content: center;
    }

    .settings-card-heading h3 {
        margin: 0;

        color: #345574;

        font-size: 16px;
        font-weight: 600;
    }

    .settings-card-heading p {
        margin: 2px 0 0;

        color: #8b99a8;

        font-size: 9px;
    }


    /* =========================================
   FORM
========================================= */

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
    }

    .settings-group {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .settings-group.full {
        grid-column: 1/-1;
    }

    .settings-group label {
        color: #47627d;
        font-size: 10px;
        font-weight: 500;
    }

    .settings-group label span {
        color: #d34848;
    }

    .settings-group input,
    .settings-group textarea {
        width: 100%;

        padding: 11px 13px;

        border: 1px solid #d9e2ec;
        border-radius: 9px;

        background: #fff;
        color: #405b76;

        font-family: "Prompt", sans-serif;
        font-size: 10px;

        outline: none;

        box-sizing: border-box;

        transition: .2s;
    }

    .settings-group input {
        height: 44px;
    }

    .settings-group textarea {
        min-height: 90px;
        resize: vertical;
    }

    .settings-group input:focus,
    .settings-group textarea:focus {
        border-color: #1768cf;

        box-shadow:
            0 0 0 3px rgba(23, 104, 207, .07);
    }

    .settings-group small {
        color: #95a3b1;
        font-size: 8px;
    }


    /* =========================================
   FIXED MEMBERSHIP FEE
========================================= */

    .fixed-fee {
        padding: 16px 18px;
        margin-bottom: 20px;

        border: 1px solid #d5e4f6;
        border-radius: 12px;

        background: #f3f8fe;

        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .fixed-fee div {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .fixed-fee i {
        color: #1768cf;
    }

    .fixed-fee span {
        color: #617992;
        font-size: 10px;
    }

    .fixed-fee strong {
        color: #1768cf;
        font-size: 20px;
        font-weight: 600;
    }

    .fixed-fee strong small {
        font-size: 10px;
        font-weight: 400;
    }

    .fee-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .fee-input-wrap input {
        width: 135px;
        height: 43px;
        padding: 0 12px;
        border: 1px solid #cfdceb;
        border-radius: 9px;
        color: #1768cf;
        background: #fff;
        font-family: "Prompt", sans-serif;
        font-size: 16px;
        font-weight: 600;
        text-align: right;
        outline: none;
    }

    .fee-input-wrap input:focus {
        border-color: #1768cf;
        box-shadow: 0 0 0 3px rgba(23, 104, 207, .09);
    }

    .fee-input-wrap small {
        color: #617992;
        font-size: 10px;
    }


    /* =========================================
   QR CODE
========================================= */

    .qr-settings {
        margin-top: 22px;
        padding-top: 20px;

        border-top: 1px solid #e8edf3;
    }

    .qr-settings h4 {
        margin: 0 0 5px;

        color: #405e7b;

        font-size: 12px;
    }

    .qr-settings>p {
        margin: 0 0 15px;

        color: #8c9aa8;

        font-size: 9px;
    }

    .qr-layout {
        display: grid;
        grid-template-columns: 210px 1fr;
        gap: 20px;

        align-items: start;
    }

    .qr-preview {
        min-height: 210px;

        padding: 12px;

        border: 1px solid #dfe7ef;
        border-radius: 12px;

        background: #f9fbfd;

        display: flex;
        align-items: center;
        justify-content: center;
    }

    .qr-preview img {
        width: 180px;
        height: 180px;

        object-fit: contain;

        background: #fff;
    }

    .qr-empty {
        color: #9aa8b6;
        text-align: center;
    }

    .qr-empty i {
        margin-bottom: 8px;

        display: block;

        color: #c1cbd5;

        font-size: 50px;
    }

    .qr-empty span {
        font-size: 9px;
    }

    .qr-upload-box {
        padding: 18px;

        border: 1px dashed #cad8e6;
        border-radius: 12px;

        background: #fbfdff;
    }

    .qr-upload-box>label:not(.qr-remove) {
        margin-bottom: 8px;

        color: #49657f;

        display: block;

        font-size: 10px;
        font-weight: 500;
    }

    .qr-upload-box input[type=file] {
        width: 100%;

        font-family: "Prompt", sans-serif;
        font-size: 9px;
    }

    .qr-upload-box p {
        margin: 9px 0 0;

        color: #95a3b1;

        font-size: 8px;
    }

    .qr-remove {
        margin-top: 14px;

        color: #9a5555;

        display: flex;
        align-items: center;
        gap: 7px;

        font-size: 9px;

        cursor: pointer;
    }


    /* =========================================
   SAVE BUTTON
========================================= */

    .settings-actions {
        display: flex;
        justify-content: flex-end;
    }

    .settings-save {
        min-height: 44px;
        padding: 0 21px;

        border: 0;
        border-radius: 9px;

        background: #1768cf;
        color: #fff;

        font-family: "Prompt", sans-serif;
        font-size: 10px;
        font-weight: 500;

        cursor: pointer;

        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .settings-save:hover {
        background: #0e57b2;
    }


    /* =========================================
   RESPONSIVE
========================================= */

    @media(max-width:750px) {

        .settings-grid {
            grid-template-columns: 1fr;
        }

        .settings-group.full {
            grid-column: auto;
        }

        .qr-layout {
            grid-template-columns: 1fr;
        }

        .qr-preview {
            max-width: 230px;
        }

    }


    @media(max-width:600px) {

        .settings-top {
            align-items: flex-start;
            flex-direction: column;
        }

        .back-dashboard-btn {
            min-height: 38px;
        }

    }
</style>


<div class="settings-page">


    <!-- =====================================
         PAGE TOP
    ====================================== -->

    <div class="settings-top">


        <!-- ชื่อหน้า -->

        <div class="settings-heading">

            <span>
                WEBSITE SETTINGS
            </span>

            <h2>
                ตั้งค่าเว็บไซต์
            </h2>

            <p>
                จัดการข้อมูลเว็บไซต์และข้อมูลรับชำระค่าสมัครสมาชิก
            </p>

        </div>


        <!-- กลับ Dashboard -->

        <a
            href="<?= BC_BASE_PATH ?>/admin/index.php"
            class="back-dashboard-btn">

            <i class="fa-solid fa-arrow-left"></i>

            กลับแดชบอร์ด

        </a>


    </div>


    <!-- =====================================
         MESSAGE
    ====================================== -->

    <?php if ($success): ?>

        <div class="settings-alert success">

            <i class="fa-solid fa-circle-check"></i>

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="settings-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         FORM
    ====================================== -->

    <form
        action="save.php"
        method="POST"
        enctype="multipart/form-data">


        <!-- CSRF -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrfToken) ?>">


        <!-- =================================
             ข้อมูลทั่วไป
        ================================== -->

        <div class="settings-card">


            <div class="settings-card-heading">

                <div class="settings-icon">

                    <i class="fa-solid fa-building"></i>

                </div>


                <div>

                    <h3>
                        ข้อมูลทั่วไป
                    </h3>

                    <p>
                        ข้อมูลหลักของ Bizcomp Club
                    </p>

                </div>

            </div>


            <div class="settings-grid">


                <!-- ชื่อชมรม -->

                <div class="settings-group">

                    <label>

                        ชื่อชมรม

                        <span>*</span>

                    </label>

                    <input
                        type="text"
                        name="club_name"
                        value="<?= htmlspecialchars($settings['club_name']) ?>"
                        required>

                </div>


                <!-- เบอร์โทร -->

                <div class="settings-group">

                    <label>
                        เบอร์โทรศัพท์
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars($settings['phone']) ?>"
                        placeholder="เช่น 043-xxx-xxx">

                </div>


                <!-- Email -->

                <div class="settings-group">

                    <label>
                        อีเมล
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($settings['email']) ?>"
                        placeholder="example@email.com">

                </div>


                <!-- ที่อยู่ -->

                <div class="settings-group full">

                    <label>
                        ที่อยู่
                    </label>

                    <textarea
                        name="address"><?= htmlspecialchars($settings['address']) ?></textarea>

                </div>


            </div>

        </div>


        <!-- =================================
             SOCIAL + MAP
        ================================== -->

        <div class="settings-card">


            <div class="settings-card-heading">

                <div class="settings-icon">

                    <i class="fa-solid fa-share-nodes"></i>

                </div>


                <div>

                    <h3>
                        Social Media และ Google Maps
                    </h3>

                    <p>
                        ช่องทางติดต่อที่แสดงในหน้าเว็บไซต์
                    </p>

                </div>

            </div>


            <div class="settings-grid">


                <!-- Facebook -->

                <div class="settings-group">

                    <label>
                        Facebook
                    </label>

                    <input
                        type="url"
                        name="facebook"
                        value="<?= htmlspecialchars($settings['facebook']) ?>"
                        placeholder="https://facebook.com/...">

                </div>


                <!-- TikTok -->

                <div class="settings-group">

                    <label>
                        TikTok
                    </label>

                    <input
                        type="url"
                        name="tiktok"
                        value="<?= htmlspecialchars($settings['tiktok']) ?>"
                        placeholder="https://tiktok.com/@...">

                </div>


                <!-- Google Maps -->

                <div class="settings-group full">

                    <label>
                        Google Maps
                    </label>

                    <input
                        type="url"
                        name="google_maps_url"
                        value="<?= htmlspecialchars($settings['google_maps_url']) ?>"
                        placeholder="https://maps.app.goo.gl/...">

                </div>


            </div>

        </div>


        <!-- =================================
             PAYMENT SETTINGS
        ================================== -->

        <div class="settings-card">


            <div class="settings-card-heading">

                <div class="settings-icon">

                    <i class="fa-solid fa-money-bill-transfer"></i>

                </div>


                <div>

                    <h3>
                        การรับชำระค่าสมัครสมาชิก
                    </h3>

                    <p>
                        ข้อมูลที่สมาชิกจะเห็นในหน้าสมัครสมาชิก
                    </p>

                </div>

            </div>


            <!-- =================================
                 ค่าสมัครสมาชิก
            ================================== -->

            <div class="fixed-fee">


                <div>

                    <i class="fa-solid fa-coins"></i>

                    <span>

                        ค่าสมัครสมาชิก

                        <br>

                        กำหนดตายตัว ไม่มีการเพิ่มหรือลด

                    </span>

                </div>


                <label class="fee-input-wrap">
                    <input
                        type="number"
                        name="membership_fee"
                        min="0"
                        max="20"
                        step="0.01"
                        value="20.00"
                        readonly
                        required
                    >
                    <small>บาท (ตายตัว)</small>
                </label>


            </div>


            <!-- =================================
                 BANK INFORMATION
            ================================== -->

            <div class="settings-grid">


                <!-- ธนาคาร -->

                <div class="settings-group">

                    <label>
                        ธนาคาร
                    </label>

                    <input
                        type="text"
                        name="bank_name"
                        value="<?= htmlspecialchars($settings['bank_name']) ?>"
                        placeholder="เช่น ธนาคารกรุงไทย">

                </div>


                <!-- ชื่อบัญชี -->

                <div class="settings-group">

                    <label>
                        ชื่อบัญชี
                    </label>

                    <input
                        type="text"
                        name="bank_account_name"
                        value="<?= htmlspecialchars($settings['bank_account_name']) ?>"
                        placeholder="ชื่อเจ้าของบัญชี">

                </div>


                <!-- เลขบัญชี -->

                <div class="settings-group">

                    <label>
                        เลขบัญชี
                    </label>

                    <input
                        type="text"
                        name="bank_account_number"
                        value="<?= htmlspecialchars($settings['bank_account_number']) ?>"
                        placeholder="xxx-x-xxxxx-x">

                </div>


                <!-- พร้อมเพย์ -->

                <div class="settings-group">

                    <label>
                        พร้อมเพย์
                    </label>

                    <input
                        type="text"
                        name="promptpay_number"
                        value="<?= htmlspecialchars($settings['promptpay_number']) ?>"
                        placeholder="เบอร์โทรศัพท์หรือเลขพร้อมเพย์">

                </div>


            </div>


            <!-- =================================
                 QR CODE
            ================================== -->

            <div class="qr-settings">


                <h4>
                    QR Code สำหรับชำระเงิน
                </h4>


                <p>
                    เมื่ออัปโหลดแล้ว QR Code
                    จะไปแสดงในหน้าสมัครสมาชิกอัตโนมัติ
                </p>


                <div class="qr-layout">


                    <!-- =================================
                         QR PREVIEW
                    ================================== -->

                    <div class="qr-preview">


                        <?php if ($qrExists): ?>


                            <img
                                src="<?= htmlspecialchars($qrUrl) ?>?v=<?= time() ?>"
                                alt="QR Code ปัจจุบัน">


                        <?php else: ?>


                            <div class="qr-empty">

                                <i class="fa-solid fa-qrcode"></i>

                                <span>
                                    ยังไม่มี QR Code
                                </span>

                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- =================================
                         QR UPLOAD
                    ================================== -->

                    <div class="qr-upload-box">


                        <label>
                            เลือกรูป QR Code
                        </label>


                        <input
                            type="file"
                            name="payment_qr"
                            accept=".png,image/png">


                        <p>
                            ใช้ไฟล์ PNG เท่านั้น
                            ขนาดไม่เกิน 3 MB
                        </p>


                        <?php if ($qrExists): ?>


                            <label class="qr-remove">

                                <input
                                    type="checkbox"
                                    name="remove_payment_qr"
                                    value="1">

                                ลบ QR Code ปัจจุบัน

                            </label>


                        <?php endif; ?>


                    </div>


                </div>

            </div>

        </div>


        <!-- =================================
             SAVE
        ================================== -->

        <div class="settings-actions">

            <button
                type="submit"
                class="settings-save">

                <i class="fa-solid fa-floppy-disk"></i>

                บันทึกการตั้งค่า
            </button>
        </div>
    </form>
</div>
<?php
include '../includes/admin_footer.php';
?>
