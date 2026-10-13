<?php
/** admin_recommend.php — แอดมินเขียนคำแนะนำหนังสือให้สมาชิก (แสดงในหน้า "แนะนำ") */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_admin();
$errors = [];
$d = ['title' => '', 'author' => '', 'reason' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post_str('action') === 'delete') {
        db_exec("DELETE FROM recommendations WHERE id = ?", 'i', [post_int('id')]);
        flash('ลบคำแนะนำแล้ว');
        redirect('admin_recommend.php');
    }
    $d = ['title' => post_str('title'), 'author' => post_str('author'), 'reason' => post_str('reason')];
    if ($d['title'] === '' || mb_strlen($d['title']) > 150) $errors[] = 'กรุณากรอกชื่อหนังสือ (ไม่เกิน 150 ตัวอักษร)';
    if (mb_strlen($d['author']) > 100) $errors[] = 'ชื่อผู้แต่งต้องไม่เกิน 100 ตัวอักษร';
    if ($d['reason'] === '' || mb_strlen($d['reason']) > 500) $errors[] = 'กรุณาเขียนเหตุผลที่แนะนำ (ไม่เกิน 500 ตัวอักษร)';
    if (!$errors) {
        db_exec("INSERT INTO recommendations (admin_id, title, author, reason) VALUES (?, ?, ?, ?)", 'isss',
                [(int)current_user_id(), $d['title'], $d['author'], $d['reason']]);
        flash('เพิ่มคำแนะนำแล้ว');
        redirect('admin_recommend.php');
    }
}
$list = db_all("SELECT * FROM recommendations ORDER BY id DESC");

page_header('คำแนะนำหนังสือ', 'admin');
admin_tabs('rec');
?>
<div class="card">
  <h1>เพิ่มคำแนะนำหนังสือ</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="form-group"><label>ชื่อหนังสือ</label><input type="text" name="title" value="<?= e($d['title']) ?>" maxlength="150" required></div>
    <div class="form-group"><label>ผู้แต่ง</label><input type="text" name="author" value="<?= e($d['author']) ?>" maxlength="100"></div>
    <div class="form-group"><label>เหตุผลที่แนะนำ</label><textarea name="reason" maxlength="500" required><?= e($d['reason']) ?></textarea></div>
    <button class="btn" type="submit">+ เพิ่มคำแนะนำ</button>
  </form>
</div>
<div class="card">
  <h1>คำแนะนำทั้งหมด</h1>
  <?php if (!$list): ?><p class="empty">ยังไม่มีคำแนะนำ</p><?php endif; ?>
  <?php foreach ($list as $r): ?>
    <div class="notif"><b><?= e($r['title']) ?></b> <span class="muted">— <?= e($r['author']) ?></span><br><?= nl2br(e($r['reason'])) ?>
      <form method="post" class="inline" data-confirm="ลบคำแนะนำนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form></div>
  <?php endforeach; ?>
</div>
<?php page_footer();
