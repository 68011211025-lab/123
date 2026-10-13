<?php
/** delete.php — ลบประกาศ (POST + CSRF + เจ้าของเท่านั้น ตรวจใน SQL ด้วย owner_id) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

start_secure_session();
require_login();
require_post();

$id = post_int('id');
$owner_id = current_user_id();

$book = db_one("SELECT cover FROM books WHERE id = ? AND owner_id = ?", 'ii', [$id, $owner_id]);
if (!$book) abort(404, 'ไม่พบหนังสือหรือคุณไม่มีสิทธิ์ลบ');

// ห้ามลบถ้ามีการแลกที่ยังไม่จบเกี่ยวข้องกับหนังสือเล่มนี้
$active = db_one("SELECT id FROM exchanges WHERE status IN ('pending','accepted') AND (target_book_id = ? OR offered_book_id = ?) LIMIT 1", 'ii', [$id, $id]);
if ($active) {
    flash('ลบไม่ได้ เพราะมีรายการแลกเปลี่ยนที่ยังไม่เสร็จสิ้น', 'error');
    redirect('my_books.php');
}

db_exec("DELETE FROM books WHERE id = ? AND owner_id = ?", 'ii', [$id, $owner_id]);
delete_upload($book['cover']);
flash('ลบประกาศแล้ว');
redirect('my_books.php');
