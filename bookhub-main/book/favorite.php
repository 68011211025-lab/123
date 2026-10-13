<?php
/** favorite.php — บันทึก/เลิกบันทึกหนังสือที่สนใจ (POST + CSRF) และแจ้งเตือนเจ้าของเมื่อมีคนสนใจ */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

start_secure_session();
require_login();
require_post();

$uid = (int)current_user_id();
$bid = post_int('book_id');
$b = db_one("SELECT id, title, owner_id, status FROM books WHERE id = ?", 'i', [$bid]);
if (!$b) abort(404, 'ไม่พบหนังสือ');

if ((int)$b['owner_id'] === $uid) {
    flash('ไม่สามารถบันทึกหนังสือของตัวเองได้', 'error');
} elseif (db_one("SELECT 1 FROM favorites WHERE user_id = ? AND book_id = ?", 'ii', [$uid, $bid])) {
    db_exec("DELETE FROM favorites WHERE user_id = ? AND book_id = ?", 'ii', [$uid, $bid]);
    flash('เลิกบันทึกแล้ว');
} else {
    if ($b['status'] !== 'available') abort(400, 'หนังสือเล่มนี้ไม่พร้อมแล้ว');
    db_exec("INSERT IGNORE INTO favorites (user_id, book_id) VALUES (?, ?)", 'ii', [$uid, $bid]);
    notify($b['owner_id'], current_user()['full_name'] . ' สนใจหนังสือ "' . $b['title'] . '" ของคุณ', 'book.php?id=' . $bid);
    flash('บันทึกเป็นรายการที่สนใจแล้ว');
}
redirect_back('favorites.php');
