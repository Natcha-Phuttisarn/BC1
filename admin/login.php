<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_start();
require_once '../includes/db.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='admin' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password,$user['password'])) {
        if ($user['account_status'] !== 'active') {
            $error = 'บัญชีนี้ไม่สามารถใช้งานได้';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_image'] = $user['profile_image'] ?? null;
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ Admin | Bizcomp Club</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(135deg,#edf4ff,#f8faff);font-family:"Prompt",sans-serif;display:flex;align-items:center;justify-content:center}.login-card{width:min(430px,calc(100% - 30px));padding:35px;background:#fff;border:1px solid #e3eaf3;border-radius:20px;box-shadow:0 20px 55px rgba(8,44,98,.12)}.logo{width:74px;height:74px;margin:0 auto 15px}.logo img{width:100%;height:100%;object-fit:contain}.title{text-align:center}.title h1{margin:0;color:#08275e;font-size:25px}.title p{margin:4px 0 25px;color:#8a98aa;font-size:11px}.field{margin-bottom:14px}.field label{display:block;margin-bottom:6px;color:#354d70;font-size:12px}.input-box{height:47px;border:1px solid #dce4ef;border-radius:10px;display:flex;align-items:center}.input-box i{width:42px;text-align:center;color:#8191a7}.input-box input{width:100%;height:100%;border:0;outline:0;background:transparent;font-family:inherit;padding-right:12px}.btn{width:100%;height:48px;margin-top:5px;border:0;border-radius:10px;background:#073a97;color:#fff;font-family:inherit;font-size:13px;cursor:pointer}.btn:hover{background:#052e79}.error{padding:10px 12px;margin-bottom:15px;background:#fff0f0;color:#bd3e3e;border-radius:8px;font-size:12px}.back{display:block;margin-top:18px;text-align:center;color:#58739c;font-size:11px;text-decoration:none}
</style>
</head>
<body>
<div class="login-card">
<div class="logo"><img src="../assets/images/logo.png" alt="Bizcomp Club"></div>
<div class="title"><h1>ผู้ดูแลระบบ</h1><p>BIZCOMP CLUB ADMINISTRATION</p></div>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<div class="field"><label>อีเมล</label><div class="input-box"><i class="fa-regular fa-envelope"></i><input type="email" name="email" placeholder="กรอกอีเมล" required></div></div>
<div class="field"><label>รหัสผ่าน</label><div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="password" placeholder="กรอกรหัสผ่าน" required></div></div>
<button type="submit" class="btn">เข้าสู่ระบบ</button>
</form>
<a href="../index.php" class="back">← กลับหน้าเว็บไซต์</a>
</div>
</body>
</html>
