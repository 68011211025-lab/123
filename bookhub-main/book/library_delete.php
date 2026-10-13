<?php
/** library_delete.php — ลบรายการในห้องสมุด (POST + CSRF + เจ้าของเท่านั้น) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

start_secure_session();
require_login();
require_post();

$n = db_exec("DELETE FROM library_items WHERE id = ? AND user_id = ?", 'ii', [post_int('id'), (int)current_user_id()]);
flash($n > 0 ? 'ลบรายการแล้ว' : 'ไม่พบรายการหรือคุณไม่มีสิทธิ์ลบ', $n > 0 ? 'ok' : 'error');
redirect('library.php');
