<?php
/**
 * exchange_action.php — เปลี่ยนสถานะการแลก (POST + CSRF)
 * ทุกคำสั่งตรวจสิทธิ์ฝั่งเซิร์ฟเวอร์: เฉพาะเจ้าของ/ผู้เสนอของรายการนั้นเท่านั้น และตรวจสถานะปัจจุบันก่อนเปลี่ยน
 *   accept   — เจ้าของหนังสือ  : pending  -> accepted (กำลังแลก) + จองหนังสือทั้งสองเล่ม
 *   reject   — เจ้าของหนังสือ  : pending  -> rejected
 *   cancel   — ผู้เสนอ (ตอน pending) หรือทั้งสองฝ่าย (ตอน accepted)
 *   confirm  — ทั้งสองฝ่าย    : accepted -> completed เมื่อยืนยันครบทั้งคู่
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

start_secure_session();
require_login();
require_post();

$uid = (int)current_user_id();
$me  = current_user()['full_name'];
$id  = post_int('id');
$action = post_str('action');

$ex = db_one("SELECT e.*, t.title AS target_title, o.title AS offered_title
              FROM exchanges e
              JOIN books t ON t.id = e.target_book_id
              JOIN books o ON o.id = e.offered_book_id
              WHERE e.id = ?", 'i', [$id]);
if (!$ex) abort(404, 'ไม่พบรายการแลกเปลี่ยน');

$isOwner = (int)$ex['owner_id'] === $uid;
$isReq   = (int)$ex['requester_id'] === $uid;
if (!$isOwner && !$isReq) abort(403, 'คุณไม่มีสิทธิ์จัดการรายการนี้');
$other = $isOwner ? (int)$ex['requester_id'] : (int)$ex['owner_id'];
$label = '"' . $ex['offered_title'] . '" ⇄ "' . $ex['target_title'] . '"';

global $conn;
$conn->begin_transaction();
try {
    // ล็อกแถวและอ่านสถานะล่าสุดอีกครั้งภายใน transaction
    $cur = db_one("SELECT status, owner_confirmed, requester_confirmed FROM exchanges WHERE id = ? FOR UPDATE", 'i', [$id]);
    $st = $cur['status'];

    if ($action === 'accept' && $isOwner && $st === 'pending') {
        // หนังสือทั้งสองเล่มต้องยังว่างอยู่
        $ok = db_exec("UPDATE books SET status = 'reserved' WHERE id IN (?, ?) AND status = 'available'", 'ii', [$ex['target_book_id'], $ex['offered_book_id']]);
        if ($ok !== 2) {
            $conn->rollback();
            flash('หนังสือบางเล่มไม่ว่างแล้ว จึงยอมรับไม่ได้', 'error');
            redirect('exchange.php');
        }
        db_exec("UPDATE exchanges SET status = 'accepted' WHERE id = ?", 'i', [$id]);
        // ข้อเสนออื่นที่ติดหนังสือสองเล่มนี้ ปฏิเสธอัตโนมัติ
        $others = db_all("SELECT id, requester_id FROM exchanges WHERE status = 'pending' AND id <> ?
                          AND (target_book_id IN (?, ?) OR offered_book_id IN (?, ?))",
                         'iiiii', [$id, $ex['target_book_id'], $ex['offered_book_id'], $ex['target_book_id'], $ex['offered_book_id']]);
        foreach ($others as $o) {
            db_exec("UPDATE exchanges SET status = 'rejected' WHERE id = ?", 'i', [$o['id']]);
            notify($o['requester_id'], 'ข้อเสนอแลกของคุณถูกปฏิเสธ เนื่องจากหนังสือถูกจองแล้ว', 'exchange.php');
        }
        notify($other, $me . ' ยอมรับข้อเสนอแลก ' . $label . ' — สถานะ: กำลังแลก', 'exchange.php');
        flash('ยอมรับข้อเสนอแล้ว สถานะ: กำลังแลก');

    } elseif ($action === 'reject' && $isOwner && $st === 'pending') {
        db_exec("UPDATE exchanges SET status = 'rejected' WHERE id = ?", 'i', [$id]);
        notify($other, $me . ' ปฏิเสธข้อเสนอแลก ' . $label, 'exchange.php');
        flash('ปฏิเสธข้อเสนอแล้ว');

    } elseif ($action === 'cancel' && (($isReq && $st === 'pending') || $st === 'accepted')) {
        if ($st === 'accepted') {
            db_exec("UPDATE books SET status = 'available' WHERE id IN (?, ?) AND status = 'reserved'", 'ii', [$ex['target_book_id'], $ex['offered_book_id']]);
        }
        db_exec("UPDATE exchanges SET status = 'cancelled' WHERE id = ?", 'i', [$id]);
        notify($other, $me . ' ยกเลิกรายการแลก ' . $label, 'exchange.php');
        flash('ยกเลิกรายการแล้ว');

    } elseif ($action === 'confirm' && $st === 'accepted') {
        $col = $isOwner ? 'owner_confirmed' : 'requester_confirmed';
        db_exec("UPDATE exchanges SET $col = 1 WHERE id = ?", 'i', [$id]);
        $now = db_one("SELECT owner_confirmed, requester_confirmed FROM exchanges WHERE id = ?", 'i', [$id]);
        if ($now['owner_confirmed'] && $now['requester_confirmed']) {
            db_exec("UPDATE exchanges SET status = 'completed' WHERE id = ?", 'i', [$id]);
            db_exec("UPDATE books SET status = 'closed' WHERE id IN (?, ?)", 'ii', [$ex['target_book_id'], $ex['offered_book_id']]);
            notify($other, 'การแลกเปลี่ยน ' . $label . ' สำเร็จแล้ว', 'exchange.php');
            flash('การแลกเปลี่ยนสำเร็จ!');
        } else {
            notify($other, $me . ' ยืนยันว่าได้รับหนังสือแล้ว ' . $label . ' — รอคุณยืนยัน', 'exchange.php');
            flash('ยืนยันแล้ว รออีกฝ่ายยืนยัน');
        }

    } else {
        $conn->rollback();
        flash('ไม่สามารถทำรายการนี้ได้ในสถานะปัจจุบัน', 'error');
        redirect('exchange.php');
    }
    $conn->commit();
} catch (Throwable $t) {
    $conn->rollback();
    throw $t;
}
redirect('exchange.php');
