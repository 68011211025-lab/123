<?php
/**
 * chat.php — แชตสอบถามรายละเอียดหนังสือ (1 ห้อง = หนังสือ 1 เล่ม + คู่สนทนา 1 คน)
 * สิทธิ์: ดู/ส่งได้เฉพาะห้องที่ตัวเองเป็นผู้ร่วมสนทนา (ตรวจ sender/receiver ใน SQL)
 * ผู้สนใจเริ่มแชตได้กับเจ้าของประกาศเท่านั้น เจ้าของตอบได้เฉพาะคนที่เคยส่งข้อความมา
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();
$errors = [];

$book_id = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('book') : get_int('book');
$with    = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('with') : get_int('with');
$book = null; $peer = null;

if ($book_id && $with) {
    $book = db_one("SELECT id, title, owner_id FROM books WHERE id = ?", 'i', [$book_id]);
    $peer = db_one("SELECT id, full_name FROM users WHERE id = ? AND is_banned = 0", 'i', [$with]);
    if (!$book || !$peer || $with === $uid) abort(404, 'ไม่พบห้องสนทนา');
    $isOwner = (int)$book['owner_id'] === $uid;
    if ($isOwner) {
        // เจ้าของคุยได้เฉพาะกับคนที่เคยส่งข้อความมาเรื่องเล่มนี้
        $has = db_one("SELECT 1 FROM messages WHERE book_id = ? AND sender_id = ? AND receiver_id = ? LIMIT 1", 'iii', [$book_id, $with, $uid]);
        if (!$has) abort(403, 'ไม่มีสิทธิ์เข้าห้องสนทนานี้');
    } elseif ((int)$book['owner_id'] !== $with) {
        abort(403, 'ไม่มีสิทธิ์เข้าห้องสนทนานี้');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $body = post_str('body');
        if ($body === '' || mb_strlen($body) > 1000) {
            $errors[] = 'ข้อความต้องมี 1-1000 ตัวอักษร';
        } else {
            $first = !db_one("SELECT 1 FROM messages WHERE book_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) LIMIT 1",
                             'iiiii', [$book_id, $uid, $with, $with, $uid]);
            db_exec("INSERT INTO messages (book_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)", 'iiis', [$book_id, $uid, $with, $body]);
            if ($first) notify($with, current_user()['full_name'] . ' ส่งข้อความสอบถามเรื่อง "' . $book['title'] . '"', 'chat.php?book=' . $book_id . '&with=' . $uid);
            redirect('chat.php?book=' . $book_id . '&with=' . $with);
        }
    }

    // เปิดอ่าน = ทำเครื่องหมายว่าอ่านแล้ว (เฉพาะข้อความที่ส่งถึงเรา)
    db_exec("UPDATE messages SET is_read = 1 WHERE book_id = ? AND sender_id = ? AND receiver_id = ?", 'iii', [$book_id, $with, $uid]);
    $msgs = db_all("SELECT * FROM messages WHERE book_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id ASC LIMIT 200",
                   'iiiii', [$book_id, $uid, $with, $with, $uid]);
} else {
    // $uid เป็น int ที่ cast แล้ว จึงแทรกลงนิพจน์ได้อย่างปลอดภัย (ใช้ในนิพจน์ GROUP BY)
    $threads = db_all("SELECT m.book_id, b.title, IF(m.sender_id = $uid, m.receiver_id, m.sender_id) AS other_id, u.full_name,
                              MAX(m.id) AS last_id, MAX(m.created_at) AS last_at, SUM(m.receiver_id = $uid AND m.is_read = 0) AS unread
                       FROM messages m
                       JOIN books b ON b.id = m.book_id
                       JOIN users u ON u.id = IF(m.sender_id = $uid, m.receiver_id, m.sender_id)
                       WHERE m.sender_id = ? OR m.receiver_id = ?
                       GROUP BY m.book_id, other_id, b.title, u.full_name
                       ORDER BY last_id DESC", 'ii', [$uid, $uid]);
}

page_header('แชต', 'chat');
if ($book && $peer): ?>
<div class="card">
  <h1>แชตเรื่อง "<?= e($book['title']) ?>"</h1>
  <p class="muted">คุยกับ <a href="profile.php?id=<?= (int)$peer['id'] ?>"><?= e($peer['full_name']) ?></a> · <a href="book.php?id=<?= (int)$book['id'] ?>">ดูประกาศ</a> · <a href="chat.php">กล่องข้อความทั้งหมด</a></p>
  <div class="chat-box">
    <?php if (!$msgs): ?><p class="muted">ยังไม่มีข้อความ เริ่มสอบถามได้เลย</p><?php endif; ?>
    <?php foreach ($msgs as $m): ?>
      <div class="msg <?= (int)$m['sender_id'] === $uid ? 'me' : '' ?>"><?= nl2br(e($m['body'])) ?><small><?= e(fmt_date($m['created_at'])) ?></small></div>
    <?php endforeach; ?>
  </div>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="small-form">
    <?= csrf_field() ?><input type="hidden" name="book" value="<?= (int)$book['id'] ?>"><input type="hidden" name="with" value="<?= (int)$peer['id'] ?>">
    <div class="form-group"><textarea name="body" maxlength="1000" placeholder="พิมพ์ข้อความ…" required></textarea></div>
    <button class="btn" type="submit">ส่งข้อความ</button>
  </form>
</div>
<?php else: ?>
<div class="card">
  <h1>กล่องข้อความ</h1>
  <?php if (!$threads): ?><p class="empty">ยังไม่มีการสนทนา — เข้าไปที่หน้าหนังสือแล้วกด "แชตถามผู้ลงประกาศ"</p><?php endif; ?>
  <?php foreach ($threads as $t): ?>
    <div class="notif <?= $t['unread'] > 0 ? 'unread' : '' ?>">
      <a href="chat.php?book=<?= (int)$t['book_id'] ?>&with=<?= (int)$t['other_id'] ?>"><?= e($t['title']) ?> — <?= e($t['full_name']) ?></a>
      <?php if ($t['unread'] > 0): ?><span class="badge-count"><?= (int)$t['unread'] ?></span><?php endif; ?>
      <div class="muted"><?= e(fmt_date($t['last_at'])) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif;
page_footer();
