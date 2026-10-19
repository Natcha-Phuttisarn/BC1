<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// image_delete.php
// ไฟล์นี้ลบรูปภาพเพิ่มเติมของ Gallery ทีละรูปโดยไม่กระทบรูปอื่น

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/gallery_files.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$imageId = (int)($_POST['image_id'] ?? 0);
$csrfToken = (string)($_POST['csrf_token'] ?? '');
$galleryId = 0;
$quarantinedFiles = [];

if (
    empty($_SESSION['gallery_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['gallery_csrf'], $csrfToken)
) {
    $_SESSION['gallery_error'] = 'คำขอลบรูปไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

if ($imageId <= 0) {
    $_SESSION['gallery_error'] = 'รหัสรูปภาพไม่ถูกต้อง';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            gi.id,
            gi.gallery_id,
            gi.image,
            g.title AS gallery_title
        FROM activity_gallery_images gi
        INNER JOIN activity_gallery g
            ON g.id = gi.gallery_id
        WHERE gi.id = ?
        LIMIT 1
    ");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new RuntimeException('ไม่พบรูปภาพที่ต้องการลบ');
    }

    $galleryId = (int)$image['gallery_id'];
    $filePath = galleryOwnedFilePath((string)$image['image']);

    if ($filePath === null || !is_file($filePath)) {
        throw new RuntimeException('ไม่พบไฟล์รูปภาพในระบบ จึงยังไม่ได้ลบข้อมูล');
    }

    $quarantinedFiles = galleryQuarantineFiles([(string)$image['image']]);
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("
        DELETE FROM activity_gallery_images
        WHERE id = ?
          AND gallery_id = ?
    ");
    $deleteStmt->execute([$imageId, $galleryId]);

    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('ไม่สามารถลบรูปภาพได้');
    }

    $pdo->commit();
    galleryDeleteQuarantinedFiles($quarantinedFiles);

    logAdminAction(
        $pdo,
        'delete',
        'activity_gallery_image',
        $imageId,
        'ลบรูปเพิ่มเติมจาก Gallery: ' . $image['gallery_title'],
        $image,
        null
    );

    $_SESSION['gallery_success'] = 'ลบรูปภาพเพิ่มเติมเรียบร้อยแล้ว';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    galleryRestoreQuarantinedFiles($quarantinedFiles);
    if (get_class($e) !== RuntimeException::class) {
        error_log('GALLERY IMAGE DELETE ERROR: ' . $e->getMessage());
    }
    $_SESSION['gallery_error'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'ไม่สามารถลบรูปภาพได้ กรุณาลองใหม่อีกครั้ง';
}

$location = $galleryId > 0
    ? 'form.php?id=' . $galleryId
    : 'index.php';

header('Location: ' . $location);
exit;
