<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// save.php
// ไฟล์นี้ใช้สำหรับบันทึกข้อมูลบุคลากรจากหน้า form.php ลงฐานข้อมูล

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;
}


$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['team_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['team_csrf'], $csrfToken)
) {
    $_SESSION['team_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$type = $_POST['type'] ?? 'committee';

$name = trim(
    $_POST['name'] ?? ''
);

$position = trim(
    $_POST['position'] ?? ''
);

$department = trim(
    $_POST['department'] ?? ''
);

$description = trim(
    $_POST['description'] ?? ''
);

$phone = trim(
    $_POST['phone'] ?? ''
);

$email = trim(
    $_POST['email'] ?? ''
);

$startedAt = trim($_POST['started_at'] ?? '');
$endedAt = trim($_POST['ended_at'] ?? '');

$showPhone = isset($_POST['show_phone']) && $phone !== '' ? 1 : 0;
$showEmail = isset($_POST['show_email']) && $email !== '' ? 1 : 0;

$displayOrder = max(
    1,
    (int)($_POST['display_order'] ?? 1)
);

$status =
    $_POST['status'] ?? 'active';

$removeImage = isset($_POST['remove_image']);


// =========================================
// ตรวจข้อมูล
// =========================================

if (
    $name === '' ||
    $position === ''
) {

    $_SESSION['team_error'] =
        'กรุณากรอกชื่อและตำแหน่ง';

    header(
        'Location: form.php' .
        ($id > 0 ? '?id=' . $id : '')
    );

    exit;
}


if (!in_array(
    $type,
    ['advisor', 'committee'],
    true
)) {

    $type = 'committee';
}


if (!in_array(
    $status,
    ['active', 'hidden', 'former'],
    true
)) {

    $status = 'active';
}

foreach (
    [
        'วันที่เริ่มทำหน้าที่' => $startedAt,
        'วันที่สิ้นสุดหน้าที่' => $endedAt
    ] as $label => $dateValue
) {
    if ($dateValue === '') {
        continue;
    }

    $date = DateTime::createFromFormat('Y-m-d', $dateValue);

    if (!$date || $date->format('Y-m-d') !== $dateValue) {
        $_SESSION['team_error'] = $label . 'ไม่ถูกต้อง';
        header('Location: form.php' . ($id > 0 ? '?id=' . $id : ''));
        exit;
    }
}

if ($startedAt !== '' && $endedAt !== '' && $endedAt < $startedAt) {
    $_SESSION['team_error'] =
        'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มทำหน้าที่';
    header('Location: form.php' . ($id > 0 ? '?id=' . $id : ''));
    exit;
}

if ($status === 'active') {
    $endedAt = '';
} elseif ($status === 'former' && $endedAt === '') {
    $endedAt = date('Y-m-d');
}


if (
    $email !== '' &&
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    $_SESSION['team_error'] =
        'รูปแบบอีเมลไม่ถูกต้อง';

    header(
        'Location: form.php' .
        ($id > 0 ? '?id=' . $id : '')
    );

    exit;
}

$normalizedPhone = normalizePhoneNumber($phone);

if ($normalizedPhone === false) {
    $_SESSION['team_error'] =
        'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก';
    header('Location: form.php' . ($id > 0 ? '?id=' . $id : ''));
    exit;
}

$phone = $normalizedPhone ?? '';
$showPhone = $showPhone && $phone !== '' ? 1 : 0;


// =========================================
// Upload รูป
// =========================================

$imagePath = null;

$newFilePath = null;


if (
    isset($_FILES['image']) &&
    $_FILES['image']['error']
        !== UPLOAD_ERR_NO_FILE
) {

    $file = $_FILES['image'];


    if (
        $file['error']
        !== UPLOAD_ERR_OK
    ) {

        $_SESSION['team_error'] =
            'เกิดข้อผิดพลาดในการอัปโหลดรูป';

        header(
            'Location: form.php' .
            ($id > 0 ? '?id=' . $id : '')
        );

        exit;
    }


    if (
        $file['size']
        > 5 * 1024 * 1024
    ) {

        $_SESSION['team_error'] =
            'รูปต้องมีขนาดไม่เกิน 5 MB';

        header(
            'Location: form.php' .
            ($id > 0 ? '?id=' . $id : '')
        );

        exit;
    }


    $mime =
        mime_content_type(
            $file['tmp_name']
        );


    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];


    if (
        !isset($allowed[$mime])
    ) {

        $_SESSION['team_error'] =
            'รองรับเฉพาะ JPG PNG และ WEBP';

        header(
            'Location: form.php' .
            ($id > 0 ? '?id=' . $id : '')
        );

        exit;
    }


    if (
        @getimagesize(
            $file['tmp_name']
        ) === false
    ) {

        $_SESSION['team_error'] =
            'ไฟล์ที่เลือกไม่ใช่รูปภาพ';

        header(
            'Location: form.php' .
            ($id > 0 ? '?id=' . $id : '')
        );

        exit;
    }


    $uploadDir =
        __DIR__ .
        '/../../uploads/personnel/';


    if (!is_dir($uploadDir)) {

        mkdir(
            $uploadDir,
            0775,
            true
        );
    }


    $fileName =
        'person_' .
        bin2hex(
            random_bytes(10)
        ) .
        '.' .
        $allowed[$mime];


    $target =
        $uploadDir .
        $fileName;


    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $target
        )
    ) {

        $_SESSION['team_error'] =
            'ไม่สามารถบันทึกรูปได้';

        header(
            'Location: form.php' .
            ($id > 0 ? '?id=' . $id : '')
        );

        exit;
    }


    $imagePath =
        'uploads/personnel/' .
        $fileName;


    $newFilePath = $target;
}


// =========================================
// Save
// =========================================

try {

    if ($id > 0) {

        $check = $pdo->prepare("
            SELECT *
            FROM personnel
            WHERE id = ?
            LIMIT 1
        ");

        $check->execute([$id]);

        $existing =
            $check->fetch();


        if (!$existing) {

            throw new Exception(
                'ไม่พบข้อมูลบุคลากร'
            );
        }

        if ($newFilePath === null) {
            $imagePath = $removeImage
                ? null
                : ($existing['image'] ?? null);
        }


        $stmt = $pdo->prepare("
            UPDATE personnel
            SET
                type = ?,
                name = ?,
                position = ?,
                department = ?,
                description = ?,
                phone = ?,
                email = ?,
                image = ?,
                started_at = ?,
                ended_at = ?,
                show_phone = ?,
                show_email = ?,
                display_order = ?,
                status = ?
            WHERE id = ?
        ");


        $stmt->execute([
            $type,
            $name,
            $position,
            $department ?: null,
            $description ?: null,
            $phone ?: null,
            $email ?: null,
            $imagePath ?: null,
            $startedAt !== '' ? $startedAt : null,
            $endedAt !== '' ? $endedAt : null,
            $showPhone,
            $showEmail,
            $displayOrder,
            $status,
            $id
        ]);


        if (
            ($newFilePath || $removeImage) &&
            !empty($existing['image']) &&
            $existing['image']
                !== $imagePath
        ) {

            $oldFile =
                __DIR__ .
                '/../../' .
                ltrim(
                    $existing['image'],
                    '/'
                );


            if (is_file($oldFile)) {

                @unlink($oldFile);
            }
        }


        $_SESSION['team_success'] =
            'แก้ไขข้อมูลบุคลากรเรียบร้อยแล้ว';

        logAdminAction($pdo, 'update', 'personnel', $id, 'แก้ไขข้อมูลบุคลากร: ' . $name, $existing, ['name'=>$name, 'position'=>$position, 'status'=>$status]);


    } else {


        $stmt = $pdo->prepare("
            INSERT INTO personnel (
                type,
                name,
                position,
                department,
                description,
                phone,
                email,
                image,
                started_at,
                ended_at,
                show_phone,
                show_email,
                display_order,
                status
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");


        $stmt->execute([
            $type,
            $name,
            $position,
            $department ?: null,
            $description ?: null,
            $phone ?: null,
            $email ?: null,
            $imagePath ?: null,
            $startedAt !== '' ? $startedAt : null,
            $endedAt !== '' ? $endedAt : null,
            $showPhone,
            $showEmail,
            $displayOrder,
            $status
        ]);

        $id = (int)$pdo->lastInsertId();
        logAdminAction($pdo, 'create', 'personnel', $id, 'เพิ่มข้อมูลบุคลากร: ' . $name, null, ['name'=>$name, 'position'=>$position, 'status'=>$status]);


        $_SESSION['team_success'] =
            'เพิ่มบุคลากรเรียบร้อยแล้ว';
    }


    header('Location: index.php');
    exit;


} catch (Throwable $e) {


    if (
        $newFilePath &&
        is_file($newFilePath)
    ) {

        @unlink($newFilePath);
    }


    $_SESSION['team_error'] =
        'ไม่สามารถบันทึกข้อมูลได้: ' .
        $e->getMessage();


    header(
        'Location: form.php' .
        ($id > 0 ? '?id=' . $id : '')
    );

    exit;
}
