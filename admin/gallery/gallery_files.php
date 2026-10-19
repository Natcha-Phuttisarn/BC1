<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// gallery_files.php
// ไฟล์นี้รวมฟังก์ชันตรวจสอบ อัปโหลด และลบไฟล์รูปของ Gallery อย่างปลอดภัย

function galleryUploadDirectory(): string
{
    return __DIR__ . '/../../uploads/gallery';
}

function galleryIsOwnedFileName(string $fileName): bool
{
    return basename($fileName) === $fileName
        && preg_match(
            '/^gallery_(?:(?:cover|image)_)?[0-9]{14}_[a-f0-9]{20}\.(?:jpg|png|webp|gif)$/',
            $fileName
        ) === 1;
}

function galleryOwnedFilePath(string $fileName): ?string
{
    if (!galleryIsOwnedFileName($fileName)) {
        return null;
    }

    return galleryUploadDirectory() . DIRECTORY_SEPARATOR . $fileName;
}

function galleryStoreUploadedImage(array $file, string $type): array
{
    $originalName = basename((string)($file['name'] ?? ''));
    $displayName = $originalName !== '' ? $originalName : 'ไฟล์ที่เลือก';

    if (!in_array($type, ['cover', 'image'], true)) {
        throw new RuntimeException('ประเภทรูปภาพไม่ถูกต้อง');
    }

    if (
        !isset($file['error']) ||
        is_array($file['error']) ||
        (int)$file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException('ไฟล์ "' . $displayName . '" อัปโหลดไม่สำเร็จ');
    }

    $fileSize = (int)($file['size'] ?? 0);
    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์ "' . $displayName . '" ต้องมีขนาดไม่เกิน 5 MB และไฟล์ต้องไม่ว่าง');
    }

    if (!in_array($originalExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('ไฟล์ "' . $displayName . '" รองรับเฉพาะ JPG, JPEG, PNG, WEBP และ GIF');
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('ไม่พบไฟล์อัปโหลดที่ถูกต้องสำหรับ "' . $displayName . '"');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    $allowedMimeTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif']
    ];

    if (
        !is_string($mime) ||
        !isset($allowedMimeTypes[$mime]) ||
        !in_array($originalExtension, $allowedMimeTypes[$mime], true)
    ) {
        throw new RuntimeException('ไฟล์ "' . $displayName . '" มีชนิดไฟล์หรือส่วนขยายไม่ถูกต้อง');
    }

    if (@getimagesize($tmpName) === false) {
        throw new RuntimeException('ไฟล์ "' . $displayName . '" ไม่ใช่รูปภาพที่ถูกต้อง');
    }

    $uploadDirectory = galleryUploadDirectory();

    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0775, true) &&
        !is_dir($uploadDirectory)
    ) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บรูปภาพได้');
    }

    $storedExtension = $mime === 'image/jpeg' ? 'jpg' : $originalExtension;

    do {
        $fileName = 'gallery_' . $type . '_' . date('YmdHis') . '_'
            . bin2hex(random_bytes(10)) . '.' . $storedExtension;
        $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
    } while (file_exists($filePath));

    if (!move_uploaded_file($tmpName, $filePath)) {
        throw new RuntimeException('ไม่สามารถบันทึกรูปภาพลงเซิร์ฟเวอร์ได้');
    }

    return [
        'name' => $fileName,
        'path' => $filePath
    ];
}

function galleryQuarantineFiles(array $fileNames): array
{
    $quarantined = [];

    try {
        foreach (array_unique($fileNames) as $fileName) {
            $filePath = galleryOwnedFilePath((string)$fileName);

            if ($filePath === null || !is_file($filePath)) {
                continue;
            }

            $quarantinePath = $filePath . '.deleting_' . bin2hex(random_bytes(4));

            if (!rename($filePath, $quarantinePath)) {
                throw new RuntimeException('ไม่สามารถเตรียมลบไฟล์รูปภาพได้');
            }

            $quarantined[$filePath] = $quarantinePath;
        }
    } catch (Throwable $e) {
        galleryRestoreQuarantinedFiles($quarantined);
        throw $e;
    }

    return $quarantined;
}

function galleryRestoreQuarantinedFiles(array $quarantined): void
{
    foreach ($quarantined as $originalPath => $quarantinePath) {
        if (is_file($quarantinePath) && !file_exists($originalPath)) {
            @rename($quarantinePath, $originalPath);
        }
    }
}

function galleryDeleteQuarantinedFiles(array $quarantined): void
{
    foreach ($quarantined as $quarantinePath) {
        if (is_file($quarantinePath) && !@unlink($quarantinePath)) {
            error_log('GALLERY DELETE FILE ERROR: ' . $quarantinePath);
        }
    }
}

function galleryDeleteNewFiles(array $uploadedFiles): void
{
    foreach ($uploadedFiles as $uploadedFile) {
        $filePath = (string)($uploadedFile['path'] ?? '');

        if ($filePath !== '' && is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
