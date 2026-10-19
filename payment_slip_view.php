<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

function paymentSlipViewError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    paymentSlipViewError(405, 'ไม่อนุญาตให้เรียกใช้งานด้วยวิธีนี้');
}

$sessionUserId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($sessionUserId === false || $sessionUserId === null) {
    paymentSlipViewError(401, 'กรุณาเข้าสู่ระบบก่อนเปิดหลักฐานการชำระเงิน');
}

$paymentId = filter_var(
    $_GET['payment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($paymentId === false || $paymentId === null) {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

try {
    $userStmt = $pdo->prepare("
        SELECT id, role, account_status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userStmt->execute([(int)$sessionUserId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$currentUser ||
        ($currentUser['account_status'] ?? '') !== 'active'
    ) {
        paymentSlipViewError(401, 'กรุณาเข้าสู่ระบบก่อนเปิดหลักฐานการชำระเงิน');
    }

    $paymentStmt = $pdo->prepare("
        SELECT id, user_id, payment_slip
        FROM membership_payments
        WHERE id = ?
        LIMIT 1
    ");
    $paymentStmt->execute([(int)$paymentId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    paymentSlipViewError(500, 'ไม่สามารถเปิดหลักฐานการชำระเงินได้ในขณะนี้');
}

if (!$payment || trim((string)($payment['payment_slip'] ?? '')) === '') {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

$isAdmin = ($currentUser['role'] ?? '') === 'admin';

if (
    !$isAdmin &&
    (int)$payment['user_id'] !== (int)$sessionUserId
) {
    paymentSlipViewError(403, 'คุณไม่มีสิทธิ์เปิดหลักฐานการชำระเงินนี้');
}

$uploadDirectory = realpath(__DIR__ . '/uploads/payments');

if ($uploadDirectory === false || !is_dir($uploadDirectory)) {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

$storedPath = trim((string)$payment['payment_slip']);
$relativePath = str_replace(
    ['/', '\\'],
    DIRECTORY_SEPARATOR,
    ltrim($storedPath, '/\\')
);
$filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);

$directoryPrefix =
    rtrim($uploadDirectory, DIRECTORY_SEPARATOR) .
    DIRECTORY_SEPARATOR;
$comparisonPrefix = DIRECTORY_SEPARATOR === '\\'
    ? strtolower($directoryPrefix)
    : $directoryPrefix;
$comparisonPath = is_string($filePath) && DIRECTORY_SEPARATOR === '\\'
    ? strtolower($filePath)
    : $filePath;

if (
    $filePath === false ||
    !is_string($comparisonPath) ||
    strncmp(
        $comparisonPath,
        $comparisonPrefix,
        strlen($comparisonPrefix)
    ) !== 0 ||
    !is_file($filePath) ||
    !is_readable($filePath)
) {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

$fileSize = filesize($filePath);

if ($fileSize === false) {
    paymentSlipViewError(404, 'ไม่พบหลักฐานการชำระเงิน');
}

session_write_close();

$downloadName =
    'payment-slip-' .
    (int)$paymentId .
    '.' .
    $allowedTypes[$mimeType];

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

readfile($filePath);
exit;
