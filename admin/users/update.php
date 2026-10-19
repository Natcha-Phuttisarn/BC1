<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$newPassword = $_POST['new_password'] ?? '';
$role = $_POST['role'] ?? '';
$accountStatus = $_POST['account_status'] ?? '';
$removeProfileImage = isset($_POST['remove_profile_image']);
$csrfToken = $_POST['csrf_token'] ?? '';

function adminUserFail(string $message, int $userId): void
{
    $_SESSION['user_admin_error'] = $message;
    header('Location: edit.php?id=' . $userId);
    exit;
}

if (
    empty($_SESSION['admin_user_csrf']) ||
    $csrfToken === '' ||
    !hash_equals($_SESSION['admin_user_csrf'], $csrfToken)
) {
    adminUserFail('คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง', $userId);
}

if ($userId <= 0 || $firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    adminUserFail('กรุณากรอกชื่อ นามสกุล และอีเมลให้ถูกต้อง', $userId);
}

if (!in_array($role, ['user', 'admin'], true) || !in_array($accountStatus, ['active', 'inactive', 'blocked'], true)) {
    adminUserFail('สิทธิ์หรือสถานะบัญชีไม่ถูกต้อง', $userId);
}

if ($newPassword !== '' && strlen($newPassword) < 8) {
    adminUserFail('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', $userId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, account_status, profile_image FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('ไม่พบบัญชีผู้ใช้งาน');
    }

    $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $emailStmt->execute([$email, $userId]);
    if ($emailStmt->fetch()) {
        throw new RuntimeException('อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว');
    }

    if (
        $user['role'] === 'admin' &&
        ($role !== 'admin' || $accountStatus !== 'active')
    ) {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
        if ($adminCount <= 1) {
            throw new RuntimeException('ไม่สามารถลดสิทธิ์หรือปิดบัญชีผู้ดูแลระบบคนสุดท้ายได้');
        }
    }

    $profileImage = $removeProfileImage ? null : $user['profile_image'];
    $sql = "
        UPDATE users
        SET first_name = ?, last_name = ?, email = ?, role = ?, account_status = ?, profile_image = ?
    ";
    $params = [$firstName, $lastName, $email, $role, $accountStatus, $profileImage];

    if ($newPassword !== '') {
        $sql .= ", password = ?";
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = ?";
    $params[] = $userId;
    $update = $pdo->prepare($sql);
    $update->execute($params);
    $pdo->commit();

    logAdminAction($pdo, 'update', 'user', $userId, 'แก้ไขบัญชีผู้ใช้งาน', $user, [
        'first_name'=>$firstName, 'last_name'=>$lastName, 'email'=>$email,
        'role'=>$role, 'account_status'=>$accountStatus, 'password_changed'=>$newPassword !== ''
    ]);

    if ($removeProfileImage && !empty($user['profile_image']) && basename($user['profile_image']) === $user['profile_image']) {
        $profileFile = __DIR__ . '/../../uploads/profiles/' . $user['profile_image'];
        if (is_file($profileFile)) {
            @unlink($profileFile);
        }
    }

    if ((int)$_SESSION['user_id'] === $userId) {
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['email'] = $email;
        $_SESSION['profile_image'] = $profileImage;
        $_SESSION['role'] = $role;

        if ($role !== 'admin' || $accountStatus !== 'active') {
            header(('Location: ' . BC_BASE_PATH . '/logout.php'));
            exit;
        }
    }

    $_SESSION['user_admin_success'] = 'แก้ไขบัญชีผู้ใช้งานเรียบร้อยแล้ว';
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    adminUserFail($e->getMessage(), $userId);
}
