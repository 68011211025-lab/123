<?php
/** exchange_offer.php — เสนอแลกหนังสือกับสมาชิกคนอื่น */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$target_id = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('target_book_id') : get_int('book');
$target = db_one("SELECT books.*, users.full_name FROM books JOIN users ON users.id = books.owner_id WHERE books.id = ?", 'i', [$target_id]);
if (!$target || $target['purpose'] !== 'exchange' || $target['status'] !== 'available') abort(404, 'หนังสือเล่มนี้ไม่เปิดให้แลกเปลี่ยน');
if ((int)$target['owner_id'] === $uid) abort(400, 'ไม่สามารถเสนอแลกกับหนังสือของตัวเองได้');

// หนังสือของฉันที่เปิดให้แลกและยังพร้อม
$mine = db_all("SELECT id, title, author FROM books WHERE owner_id = ? AND purpose = 'exchange' AND status = 'available' ORDER BY title", 'i', [$uid]);
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $offered_id = post_int('offered_book_id');
    $message = post_str('message');
    if (mb_strlen($message) > 500) $errors[] = 'ข้อความต้องไม่เกิน 500 ตัวอักษร';
    // ต้องเป็นหนังสือของฉันที่อยู่ในรายการแลกเท่านั้น (กันส่งเลขหนังสือของคนอื่นมา)
    $offered = null;
    foreach ($mine as $m) if ((int)$m['id'] === $offered_id) $offered = $m;
    if (!$offered) $errors[] = 'กรุณาเลือกหนังสือของคุณที่ต้องการนำมาแลก';
    if (!$errors && db_one("SELECT 1 FROM exchanges WHERE requester_id = ? AND target_book_id = ? AND offered_book_id = ? AND status IN ('pending','accepted')", 'iii', [$uid, $target_id, $offered_id])) {
        $errors[] = 'คุณได้เสนอแลกรายการนี้ไปแล้ว';
    }
    if (!$errors) {
        db_exec("INSERT INTO exchanges (requester_id, owner_id, target_book_id, offered_book_id, message) VALUES (?, ?, ?, ?, ?)",
                'iiiis', [$uid, $target['owner_id'], $target_id, $offered_id, $message]);
        notify($target['owner_id'], current_user()['full_name'] . ' เสนอแลก "' . $offered['title'] . '" กับ "' . $target['title'] . '"', 'exchange.php');
        flash('ส่งข้อเสนอแลกเปลี่ยนแล้ว รอเจ้าของตอบรับ');
        redirect('exchange.php');
    }
}

page_header('เสนอแลกหนังสือ', 'exchange');
?>
<div class="card">
  <h1>เสนอแลกกับ "<?= e($target['title']) ?>"</h1>
  <p class="muted">เจ้าของ: <?= e($target['full_name']) ?></p>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <?php if (!$mine): ?>
    <p>คุณยังไม่มีหนังสือที่เปิดให้แลก กรุณาลงประกาศประเภท "แลกเปลี่ยน" ก่อน</p>
    <a class="btn btn-add" href="create.php">+ ลงประกาศหนังสือที่ต้องการแลก</a>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="target_book_id" value="<?= (int)$target_id ?>">
    <div class="form-group"><label>เลือกหนังสือของคุณที่จะนำมาแลก</label>
      <select name="offered_book_id" required>
        <option value="">-- เลือก --</option>
        <?php foreach ($mine as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['title']) ?> — <?= e($m['author']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label>ข้อความถึงเจ้าของ (ไม่บังคับ)</label>
      <textarea name="message" maxlength="500"><?= e($message) ?></textarea></div>
    <button class="btn btn-ok" type="submit">ส่งข้อเสนอแลก</button>
    <a href="book.php?id=<?= (int)$target_id ?>">ยกเลิก</a>
  </form>
  <?php endif; ?>
</div>
<?php page_footer();
