<?php
/**
 * logout.php — ออกจากระบบ (รับเฉพาะ POST + CSRF token กันถูกบังคับให้ออกจากระบบ)
 * ขั้นตอน: 1) ล้างตัวแปร session 2) ลบคุกกี้ 3) ทำลาย session ฝั่งเซิร์ฟเวอร์ 4) กลับหน้า login
 */
require __DIR__ . '/helpers.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_check();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
