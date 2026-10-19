<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// delete.php
// ไฟล์นี้ลบ Gallery พร้อมรูปปก รูปเพิ่มเติม และข้อมูลที่เกี่ยวข้องอย่างปลอดภัย

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/gallery_files.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$csrfToken = (string)($_POST['csrf_token'] ?? '');

if (
    empty($_SESSION['gallery_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['gallery_csrf'], $csrfToken)
) {
    $_SESSION['gallery_error'] = 'คำขอลบไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

if ($id <= 0) {
    $_SESSION['gallery_error'] = 'รหัสภาพกิจกรรมไม่ถูกต้อง';
    header('Location: index.php');
    exit;
}

$quarantinedFiles = [];

try {
    $stmt = $pdo->prepare('SELECT * FROM activity_gallery WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gallery) {
        throw new RuntimeException('ไม่พบภาพกิจกรรมที่ต้องการลบ');
    }

    $imageStmt = $pdo->prepare("
        SELECT id, image
        FROM activity_gallery_images
        WHERE gallery_id = ?
        ORDER BY id ASC
    ");
    $imageStmt->execute([$id]);
    $additionalImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    $fileNames = [(string)$gallery['cover_image']];

    foreach ($additionalImages as $additionalImage) {
        $fileNames[] = (string)$additionalImage['image'];
    }

    $quarantinedFiles = galleryQuarantineFiles($fileNames);
    $pdo->beginTransaction();

    $deleteImages = $pdo->prepare('DELETE FROM activity_gallery_images WHERE gallery_id = ?');
    $deleteImages->execute([$id]);

    $deleteGallery = $pdo->prepare('DELETE FROM activity_gallery WHERE id = ?');
    $deleteGallery->execute([$id]);

    if ($deleteGallery->rowCount() !== 1) {
        throw new RuntimeException('ไม่สามารถลบภาพกิจกรรมได้');
    }

    $pdo->commit();
    galleryDeleteQuarantinedFiles($quarantinedFiles);

    logAdminAction(
        $pdo,
        'delete',
        'activity_gallery',
        $id,
        'ลบภาพกิจกรรม: ' . $gallery['title'],
        [
            'gallery' => $gallery,
            'additional_images' => $additionalImages
        ],
        null
    );

    unset($_SESSION['gallery_csrf']);
    $_SESSION['gallery_success'] = 'ลบภาพกิจกรรมและรูปภาพทั้งหมดเรียบร้อยแล้ว';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    galleryRestoreQuarantinedFiles($quarantinedFiles);
    if (get_class($e) !== RuntimeException::class) {
        error_log('GALLERY DELETE ERROR: ' . $e->getMessage());
    }
    $_SESSION['gallery_error'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'ไม่สามารถลบภาพกิจกรรมได้ กรุณาลองใหม่อีกครั้ง';
}

header('Location: index.php');
exit;
