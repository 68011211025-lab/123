<?php
/**
 * make_admin.php — สคริปต์ใช้ครั้งเดียว: ตั้ง mi21eu เป็นผู้ดูแลระบบ และลบบัญชีทดสอบเก่า
 * ใช้เสร็จแล้วต้องลบไฟล์นี้ออกจาก GitHub ทันที
 * เปิดด้วย: /make_admin.php?key=รหัสลับด้านล่าง
 */
$KEY = 'ii3dw4pyu-ja6XtCX7nNfRNGBeStIB3y';
$ADMIN_USERNAME = 'mi21eu';

$given = $_GET['key'] ?? '';
if (!is_string($given) || !hash_equals($KEY, $given)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/db.php';

try {
    // 1) ลบบัญชีทดสอบเก่าจาก setup.sql ตัวเดิม (ต้องตรงทั้งชื่อผู้ใช้และอีเมลตัวอย่าง จึงไม่ลบบัญชีจริงของใคร)
    $stmt = $conn->prepare(
        "DELETE FROM users WHERE (username = 'admin' AND email = 'admin@example.com')
                              OR (username = 'nida'  AND email = 'nida@example.com')
                              OR (username = 'anan'  AND email = 'anan@example.com')"
    );
    $stmt->execute();
    echo "ลบบัญชีทดสอบเก่า: " . $stmt->affected_rows . " บัญชี\n";
    $stmt->close();

    // 2) ตั้งผู้ใช้ที่ระบุเป็น admin
    $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE username = ?");
    $stmt->bind_param('s', $ADMIN_USERNAME);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->bind_param('s', $ADMIN_USERNAME);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo "ไม่พบผู้ใช้ชื่อ {$ADMIN_USERNAME} — ให้สมัครสมาชิกบนเว็บด้วยชื่อผู้ใช้นี้ก่อน แล้วเปิดหน้านี้อีกครั้ง\n";
    } else {
        echo "{$ADMIN_USERNAME} มีบทบาทเป็น: " . $row['role'] . "\n";
    }

    // 3) แสดงรายชื่อผู้ใช้ทั้งหมดให้ตรวจ
    echo "\nผู้ใช้ทั้งหมดในระบบ:\n";
    $res = $conn->query("SELECT id, username, role FROM users ORDER BY id LIMIT 50");
    while ($u = $res->fetch_assoc()) {
        echo "  #" . $u['id'] . "  " . $u['username'] . "  (" . $u['role'] . ")\n";
    }
    echo "\nเสร็จแล้ว — ลบไฟล์ make_admin.php ออกจาก GitHub ทันที\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
}
