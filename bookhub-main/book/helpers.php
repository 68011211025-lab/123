<?php
/**
 * helpers.php — ฟังก์ชันกลางของระบบ (ความปลอดภัย, ฐานข้อมูล, ตัวช่วยต่าง ๆ)
 */

const CONDITIONS = [
    'new'      => 'มือหนึ่ง (ใหม่)',
    'like_new' => 'มือสอง - สภาพดีมาก',
    'good'     => 'มือสอง - สภาพดี',
    'fair'     => 'มือสอง - สภาพพอใช้',
];
const PURPOSES = ['sale' => 'ขาย', 'exchange' => 'แลกเปลี่ยน'];
const BOOK_STATUS = ['available' => 'พร้อมขาย/แลก', 'reserved' => 'กำลังแลก', 'sold' => 'ขายแล้ว', 'closed' => 'ปิดประกาศ'];
const EX_STATUS = [
    'pending' => 'รอตอบรับ', 'accepted' => 'กำลังแลก', 'completed' => 'สำเร็จ',
    'rejected' => 'ปฏิเสธ', 'cancelled' => 'ยกเลิก',
];
const READ_STATUS = ['none' => 'ยังไม่ระบุ', 'want' => 'อยากอ่าน', 'read' => 'อ่านแล้ว'];

set_exception_handler(function ($ex) {
    error_log((string)$ex);
    if (!headers_sent()) http_response_code(500);
    echo 'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง';
});

/* ---------- พื้นฐาน ---------- */
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}
function redirect($url) {
    header('Location: ' . $url);
    exit;
}
function abort($code, $msg) {
    http_response_code($code);
    exit(e($msg));
}
function money($n) {
    return '฿' . number_format((float)$n, 2);
}
function fmt_date($ts) {
    return date('d/m/Y H:i', strtotime($ts));
}

// IP ของผู้ใช้จริง: เมื่ออยู่หลัง proxy ของโฮสต์ REMOTE_ADDR จะเป็น IP ของ proxy
// ใช้ค่าขวาสุดของ X-Forwarded-For เพราะเป็นค่าที่ proxy เติมเอง (ค่าซ้ายปลอมได้)
function client_ip() {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $parts = array_map('trim', explode(',', $xff));
        $ip = end($parts);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------- Header ป้องกันการโจมตี + Session ---------- */
function send_security_headers() {
    if (headers_sent()) return;
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
function start_secure_session() {
    send_security_headers();
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
        session_start();
        // หมดอายุเมื่อไม่ได้ใช้งานเกิน 2 ชั่วโมง
        if (!empty($_SESSION['user_id']) && isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > 7200) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_active'] = time();
    }
}

/* ---------- ฐานข้อมูล (prepared statement ทุกครั้ง) ---------- */
function db_query($sql, $types = '', $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}
function db_all($sql, $types = '', $params = []) {
    $res = db_query($sql, $types, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function db_one($sql, $types = '', $params = []) {
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}
function db_exec($sql, $types = '', $params = []) {
    $stmt = db_query($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

/* ---------- ผู้ใช้ / สิทธิ์ ---------- */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
// ดึงข้อมูลผู้ใช้จากฐานข้อมูลทุกครั้ง (บทบาท/สถานะแบนมีผลทันที ไม่เชื่อค่าใน session)
function current_user() {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['user_id'])) {
        $row = db_one("SELECT id, username, email, full_name, role, bio, is_banned FROM users WHERE id = ?",
                      'i', [(int)$_SESSION['user_id']]);
        if ($row && !$row['is_banned']) $cache = $row;
    }
    return $cache;
}
function require_login() {
    if (!current_user()) {
        unset($_SESSION['user_id'], $_SESSION['full_name']);
        redirect('login.php');
    }
}
function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}
function require_admin() {
    require_login();
    if (!is_admin()) abort(403, '403 — เฉพาะผู้ดูแลระบบเท่านั้น');
}

/* ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check() {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        abort(403, 'คำขอไม่ถูกต้องหรือหมดอายุ (CSRF) กรุณากลับไปโหลดหน้าใหม่');
    }
}
// ใช้กับหน้าที่ทำงานเฉพาะ POST (เพิ่ม/ลบ/เปลี่ยนสถานะ)
function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort(405, '405 — ต้องส่งแบบ POST');
    csrf_check();
}

/* ---------- Flash / รับค่า input ---------- */
function flash($msg, $type = 'ok') {
    $_SESSION['flash'][] = [$type, $msg];
}
function post_str($key) {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function post_int($key) {
    $v = $_POST[$key] ?? '';
    return (is_string($v) && ctype_digit($v)) ? (int)$v : 0;
}
function get_int($key) {
    $v = $_GET[$key] ?? '';
    return (is_string($v) && ctype_digit($v)) ? (int)$v : 0;
}
function get_str($key) {
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
// กลับไปหน้าเดิมโดยอนุญาตเฉพาะลิงก์ภายในเว็บ (กัน open redirect)
function redirect_back($default) {
    $to = $_POST['return'] ?? $default;
    if (!is_string($to) || !preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $to)) $to = $default;
    redirect($to);
}
function return_field($path) {
    return '<input type="hidden" name="return" value="' . e($path) . '">';
}

/* ---------- แจ้งเตือน ---------- */
function notify($user_id, $message, $link = '') {
    db_exec("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)",
            'iss', [(int)$user_id, mb_substr($message, 0, 255), $link]);
}

/* ---------- อัปโหลดรูปภาพอย่างปลอดภัย ---------- */
/**
 * คืนค่า: string = ได้ชื่อไฟล์ใหม่ / null = ไม่ได้เลือกไฟล์ / false = ไฟล์ไม่ถูกต้อง
 * ตรวจ: เป็นไฟล์ที่อัปโหลดจริง, ขนาด <= 2MB, เนื้อไฟล์เป็นรูปจริง (getimagesize + finfo),
 *       อนุญาตเฉพาะ jpg/png/gif/webp, ตั้งชื่อใหม่แบบสุ่ม (ไม่ใช้ชื่อ/นามสกุลจากผู้ใช้)
 */
function safe_upload($field_name, $dest_dir = null) {
    if ($dest_dir === null) $dest_dir = __DIR__ . '/uploads';
    if (empty($_FILES[$field_name]['name'])) return null;
    if (!is_string($_FILES[$field_name]['name'])) return false;
    if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) return false;
    $tmp = $_FILES[$field_name]['tmp_name'];
    if (!is_uploaded_file($tmp)) return false;
    if ($_FILES[$field_name]['size'] > 2 * 1024 * 1024) return false;
    $info = @getimagesize($tmp);
    if ($info === false) return false;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$info['mime']])) return false;
    $fi = new finfo(FILEINFO_MIME_TYPE);
    if ($fi->file($tmp) !== $info['mime']) return false;
    $ext = $allowed[$info['mime']];
    $newname = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    if (!move_uploaded_file($tmp, $dest_dir . '/' . $newname)) return false;
    return $newname;
}
function delete_upload($name) {
    if (is_string($name) && preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $name)) {
        $p = __DIR__ . '/uploads/' . $name;
        if (is_file($p)) @unlink($p);
    }
}
