<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// save.php
// ไฟล์นี้ตรวจสอบและบันทึกข้อมูล รูปปก และรูปเพิ่มเติมของ Gallery

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/gallery_files.php';

date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$formLocation = 'form.php' . ($id > 0 ? '?id=' . $id : '');

function gallerySaveFail(string $message, string $location): void
{
    $_SESSION['gallery_error'] = $message;
    header('Location: ' . $location);
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');

if (
    empty($_SESSION['gallery_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['gallery_csrf'], $csrfToken)
) {
    gallerySaveFail('คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง', 'index.php');
}

if ($id < 0) {
    gallerySaveFail('รหัสภาพกิจกรรมไม่ถูกต้อง', 'index.php');
}

$activityIdInput = trim((string)($_POST['activity_id'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$activityDate = trim((string)($_POST['activity_date'] ?? ''));
$status = (string)($_POST['status'] ?? 'active');

$_SESSION['gallery_old'] = [
    'id' => $id,
    'activity_id' => $activityIdInput,
    'title' => $title,
    'description' => $description,
    'activity_date' => $activityDate,
    'status' => $status
];

if ($title === '') {
    gallerySaveFail('กรุณากรอกชื่อภาพหรือชื่อกิจกรรม', $formLocation);
}

if (mb_strlen($title, 'UTF-8') > 255) {
    gallerySaveFail('ชื่อภาพหรือชื่อกิจกรรมต้องไม่เกิน 255 ตัวอักษร', $formLocation);
}

if (mb_strlen($description, 'UTF-8') > 65535) {
    gallerySaveFail('รายละเอียดมีความยาวเกินกว่าที่ระบบรองรับ', $formLocation);
}

if ($activityDate !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $activityDate);

    if (!$date || $date->format('Y-m-d') !== $activityDate) {
        gallerySaveFail('วันที่กิจกรรมไม่ถูกต้อง', $formLocation);
    }
}

if (!in_array($status, ['active', 'inactive'], true)) {
    gallerySaveFail('สถานะภาพกิจกรรมไม่ถูกต้อง', $formLocation);
}

$activityId = null;

if ($activityIdInput !== '') {
    if (!ctype_digit($activityIdInput) || (int)$activityIdInput <= 0) {
        gallerySaveFail('กิจกรรมที่เลือกไม่ถูกต้อง', $formLocation);
    }

    $activityId = (int)$activityIdInput;
    $activityCheck = $pdo->prepare('SELECT id FROM activities WHERE id = ? LIMIT 1');
    $activityCheck->execute([$activityId]);

    if (!$activityCheck->fetchColumn()) {
        gallerySaveFail('ไม่พบกิจกรรมที่เลือก กรุณาเลือกใหม่', $formLocation);
    }
}

$existing = null;

if ($id > 0) {
    $existingStmt = $pdo->prepare('SELECT * FROM activity_gallery WHERE id = ? LIMIT 1');
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        unset($_SESSION['gallery_old']);
        gallerySaveFail('ไม่พบภาพกิจกรรมที่ต้องการแก้ไข', 'index.php');
    }
}

$coverFile = $_FILES['cover_image'] ?? null;
$hasNewCover = is_array($coverFile)
    && isset($coverFile['error'])
    && !is_array($coverFile['error'])
    && (int)$coverFile['error'] !== UPLOAD_ERR_NO_FILE;

if (!$existing && !$hasNewCover) {
    gallerySaveFail('กรุณาเลือกรูปปกกิจกรรม', $formLocation);
}

$additionalUploadFiles = [];
$additionalFiles = $_FILES['additional_images'] ?? null;

if (is_array($additionalFiles) && isset($additionalFiles['name'])) {
    if (
        !is_array($additionalFiles['name']) ||
        !is_array($additionalFiles['tmp_name'] ?? null) ||
        !is_array($additionalFiles['error'] ?? null) ||
        !is_array($additionalFiles['size'] ?? null)
    ) {
        gallerySaveFail('ข้อมูลรูปภาพเพิ่มเติมไม่ถูกต้อง', $formLocation);
    }

    foreach ($additionalFiles['name'] as $index => $originalName) {
        $uploadError = (int)($additionalFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $additionalUploadFiles[] = [
            'name' => $originalName,
            'tmp_name' => $additionalFiles['tmp_name'][$index] ?? '',
            'error' => $uploadError,
            'size' => $additionalFiles['size'][$index] ?? 0
        ];
    }
}

$uploadedFiles = [];
$quarantinedFiles = [];
$newCoverName = null;

try {
    if ($hasNewCover) {
        $newCover = galleryStoreUploadedImage($coverFile, 'cover');
        $uploadedFiles[] = $newCover;
        $newCoverName = $newCover['name'];
    }

    $newAdditionalImages = [];

    foreach ($additionalUploadFiles as $additionalUploadFile) {
        $newImage = galleryStoreUploadedImage($additionalUploadFile, 'image');
        $uploadedFiles[] = $newImage;
        $newAdditionalImages[] = $newImage;
    }

    $filesToQuarantine = [];

    if ($existing && $newCoverName !== null && !empty($existing['cover_image'])) {
        $filesToQuarantine[] = $existing['cover_image'];
    }

    $quarantinedFiles = galleryQuarantineFiles($filesToQuarantine);
    $coverImageName = $newCoverName ?: (string)($existing['cover_image'] ?? '');

    $pdo->beginTransaction();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE activity_gallery
            SET
                title = ?,
                description = ?,
                cover_image = ?,
                activity_date = ?,
                activity_id = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title,
            $description !== '' ? $description : null,
            $coverImageName,
            $activityDate !== '' ? $activityDate : null,
            $activityId,
            $status,
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO activity_gallery
                (title, description, cover_image, activity_date, activity_id, status)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $description !== '' ? $description : null,
            $coverImageName,
            $activityDate !== '' ? $activityDate : null,
            $activityId,
            $status
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    if (!empty($newAdditionalImages)) {
        $imageInsert = $pdo->prepare("
            INSERT INTO activity_gallery_images (gallery_id, image)
            VALUES (?, ?)
        ");

        foreach ($newAdditionalImages as $newAdditionalImage) {
            $imageInsert->execute([$id, $newAdditionalImage['name']]);
        }
    }

    $pdo->commit();
    galleryDeleteQuarantinedFiles($quarantinedFiles);

    $auditValues = [
        'title' => $title,
        'activity_id' => $activityId,
        'activity_date' => $activityDate ?: null,
        'status' => $status,
        'cover_image' => $coverImageName,
        'additional_images_added' => count($newAdditionalImages)
    ];

    if ($existing) {
        logAdminAction(
            $pdo,
            'update',
            'activity_gallery',
            $id,
            'แก้ไขภาพกิจกรรม: ' . $title,
            $existing,
            $auditValues
        );
        $_SESSION['gallery_success'] = 'แก้ไขภาพกิจกรรมเรียบร้อยแล้ว';
    } else {
        logAdminAction(
            $pdo,
            'create',
            'activity_gallery',
            $id,
            'เพิ่มภาพกิจกรรม: ' . $title,
            null,
            $auditValues
        );
        $_SESSION['gallery_success'] = 'เพิ่มภาพกิจกรรมเรียบร้อยแล้ว';
    }

    unset($_SESSION['gallery_old'], $_SESSION['gallery_csrf']);
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    galleryRestoreQuarantinedFiles($quarantinedFiles);
    galleryDeleteNewFiles($uploadedFiles);

    if (get_class($e) !== RuntimeException::class) {
        error_log('GALLERY SAVE ERROR: ' . $e->getMessage());
    }
    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'ไม่สามารถบันทึกภาพกิจกรรมได้ กรุณาลองใหม่อีกครั้ง';
    gallerySaveFail($message, $formLocation);
}
