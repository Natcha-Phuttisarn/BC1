<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// save.php
// หน้านี้ใช้สำหรับบันทึกการตั้งค่าเว็บไซต์ ข้อมูลบัญชี และ QR Code

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/db.php';
require_once '../includes/admin_auth.php';


// =========================================
// ต้องเป็น POST
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;
}


// =========================================
// FUNCTION ERROR
// =========================================

function settingsError($message)
{
    $_SESSION['settings_error'] = $message;

    header('Location: index.php');
    exit;
}


// =========================================
// CSRF
// =========================================

$csrfToken =
    $_POST['csrf_token']
    ?? '';

$sessionToken =
    $_SESSION['settings_csrf']
    ?? '';


if (
    $sessionToken === ''
    ||
    !hash_equals(
        $sessionToken,
        $csrfToken
    )
) {

    settingsError(
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'
    );
}


// =========================================
// รับข้อมูล
// =========================================

$clubName =
    trim($_POST['club_name'] ?? '');

$address =
    trim($_POST['address'] ?? '');

$phone =
    trim($_POST['phone'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$facebook =
    trim($_POST['facebook'] ?? '');

$tiktok =
    trim($_POST['tiktok'] ?? '');

$googleMapsUrl =
    trim($_POST['google_maps_url'] ?? '');


// =========================================
// PAYMENT
// =========================================

$bankName =
    trim($_POST['bank_name'] ?? '');

$bankAccountName =
    trim($_POST['bank_account_name'] ?? '');

$bankAccountNumber =
    trim($_POST['bank_account_number'] ?? '');

$promptpayNumber =
    trim($_POST['promptpay_number'] ?? '');

$membershipFee = '20.00';


// =========================================
// VALIDATION
// =========================================

if ($clubName === '') {

    settingsError(
        'กรุณากรอกชื่อชมรม'
    );
}

if (
    $membershipFee === '' ||
    !is_numeric($membershipFee) ||
    (float)$membershipFee < 0 ||
    (float)$membershipFee > 100000
) {
    settingsError('กรุณากรอกค่าสมัครสมาชิกให้ถูกต้อง');
}


if (
    $email !== ''
    &&
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    settingsError(
        'รูปแบบอีเมลไม่ถูกต้อง'
    );
}


// =========================================
// ตรวจ URL
// =========================================

$urlFields = [

    'Facebook' =>
        $facebook,

    'TikTok' =>
        $tiktok,

    'Google Maps' =>
        $googleMapsUrl

];


foreach (
    $urlFields
    as $name => $url
) {

    if (
        $url !== ''
        &&
        !filter_var(
            $url,
            FILTER_VALIDATE_URL
        )
    ) {

        settingsError(
            'ลิงก์ ' .
            $name .
            ' ไม่ถูกต้อง'
        );
    }
}


// =========================================
// ข้อมูลที่จะบันทึก
// =========================================

$settings = [

    'club_name' =>
        $clubName,

    'address' =>
        $address,

    'phone' =>
        $phone,

    'email' =>
        $email,

    'facebook' =>
        $facebook,

    'tiktok' =>
        $tiktok,

    'google_maps_url' =>
        $googleMapsUrl,


    // =====================================
    // PAYMENT SETTINGS
    // =====================================

    'bank_name' =>
        $bankName,

    'bank_account_name' =>
        $bankAccountName,

    'bank_account_number' =>
        $bankAccountNumber,

    'promptpay_number' =>
        $promptpayNumber,


    // ค่าสมัครสมาชิกที่ผู้ดูแลกำหนด
    'membership_fee' =>
        number_format((float)$membershipFee, 2, '.', '')

];


// =========================================
// บันทึก SITE SETTINGS
// =========================================

try {

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        INSERT INTO site_settings (
            setting_key,
            setting_value
        )

        VALUES (
            ?,
            ?
        )

        ON DUPLICATE KEY UPDATE

            setting_value =
                VALUES(setting_value)
    ");


    foreach (
        $settings
        as $key => $value
    ) {

        $stmt->execute([
            $key,
            $value
        ]);
    }


    $pdo->commit();

    logAdminAction($pdo, 'update', 'site_settings', null, 'แก้ไขการตั้งค่าเว็บไซต์', null, $settings);


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    settingsError(
        'ไม่สามารถบันทึกข้อมูลการตั้งค่าได้'
    );
}


// =========================================
// QR CODE PATH
// =========================================

$qrDirectory =
    __DIR__ .
    '/../../assets/images';

$qrFile =
    $qrDirectory .
    '/payment-qr.png';


// =========================================
// ลบ QR
// =========================================

$removeQr =
    isset(
        $_POST['remove_payment_qr']
    );


if ($removeQr) {

    if (is_file($qrFile)) {

        @unlink($qrFile);
    }
}


// =========================================
// ตรวจว่ามี Upload QR ใหม่หรือไม่
// =========================================

if (
    isset($_FILES['payment_qr'])
    &&
    $_FILES['payment_qr']['error']
        !== UPLOAD_ERR_NO_FILE
) {


    $file =
        $_FILES['payment_qr'];


    // =====================================
    // Upload Error
    // =====================================

    if (
        $file['error']
        !== UPLOAD_ERR_OK
    ) {

        settingsError(
            'ไม่สามารถอัปโหลด QR Code ได้'
        );
    }


    // =====================================
    // SIZE
    // =====================================

    if (
        $file['size']
        > 3 * 1024 * 1024
    ) {

        settingsError(
            'QR Code ต้องมีขนาดไม่เกิน 3 MB'
        );
    }


    // =====================================
    // MIME TYPE
    // =====================================

    $finfo =
        new finfo(
            FILEINFO_MIME_TYPE
        );


    $mimeType =
        $finfo->file(
            $file['tmp_name']
        );


    if (
        $mimeType !== 'image/png'
    ) {

        settingsError(
            'กรุณาใช้ QR Code เป็นไฟล์ PNG เท่านั้น'
        );
    }


    // =====================================
    // ตรวจว่าเป็นรูปจริง
    // =====================================

    $imageInfo =
        @getimagesize(
            $file['tmp_name']
        );


    if ($imageInfo === false) {

        settingsError(
            'ไฟล์ QR Code ไม่ใช่รูปภาพที่ถูกต้อง'
        );
    }


    // =====================================
    // DIRECTORY
    // =====================================

    if (!is_dir($qrDirectory)) {

        if (
            !mkdir(
                $qrDirectory,
                0775,
                true
            )
            &&
            !is_dir($qrDirectory)
        ) {

            settingsError(
                'ไม่สามารถสร้างโฟลเดอร์สำหรับ QR Code ได้'
            );
        }
    }


    // =====================================
    // SAVE QR
    // =====================================

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $qrFile
        )
    ) {

        settingsError(
            'ไม่สามารถบันทึก QR Code ได้'
        );
    }

}


// =========================================
// SUCCESS
// =========================================

$_SESSION['settings_success'] =
    'บันทึกการตั้งค่าเว็บไซต์เรียบร้อยแล้ว';


header('Location: index.php');
exit;
