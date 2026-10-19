<?php
require_once __DIR__ . '/includes/bootstrap.php';
// logout.php
// หน้านี้ใช้สำหรับออกจากระบบและล้าง Session

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =========================================
// ล้างข้อมูล Session
// =========================================

$_SESSION = [];


// =========================================
// ลบ Session Cookie
// =========================================

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


// =========================================
// ทำลาย Session
// =========================================

session_destroy();


// =========================================
// กลับหน้าแรก
// =========================================

header(('Location: ' . BC_BASE_PATH . '/index.php'));
exit;