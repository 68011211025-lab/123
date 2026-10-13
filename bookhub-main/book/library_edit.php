<?php
/** library_edit.php — แก้ไขรายการในห้องสมุด + ให้คะแนน/เขียนรีวิว (เจ้าของรายการเท่านั้น) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('id') : get_int('id');
$it = db_one("SELECT * FROM library_items WHERE id = ? AND user_id = ?", 'ii', [$id, $uid]);   // เช็กเจ้าของใน SQL
if (!$it) abort(404, 'ไม่พบรายการ หรือคุณไม่มีสิทธิ์แก้ไข');

$errors = [];
$d = ['title' => $it['title'], 'author' => $it['author'], 'shelf' => (string)$it['shelf'], 'is_owned' => (int)$it['is_owned'],
      'read_status' => $it['read_status'], 'rating' => $it['rating'] === null ? '' : (string)$it['rating'], 'review' => (string)$it['review']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = [
        'title' => post_str('title'), 'author' => post_str('author'), 'shelf' => post_str('shelf'),
        'is_owned' => isset($_POST['is_owned']) ? 1 : 0, 'read_status' => post_str('read_status'),
        'rating' => post_str('rating'), 'review' => post_str('review'),
    ];
    if ($d['title'] === '' || mb_strlen($d['title']) > 150) $errors[] = 'กรุณากรอกชื่อหนังสือ (ไม่เกิน 150 ตัวอักษร)';
    if (mb_strlen($d['author']) > 100) $errors[] = 'ชื่อผู้แต่งต้องไม่เกิน 100 ตัวอักษร';
    if (mb_strlen($d['shelf']) > 50) $errors[] = 'ชื่อชั้นต้องไม่เกิน 50 ตัวอักษร';
    if (!isset(READ_STATUS[$d['read_status']])) $errors[] = 'สถานะการอ่านไม่ถูกต้อง';
    if ($d['rating'] !== '' && !preg_match('/^[1-5]$/', $d['rating'])) $errors[] = 'คะแนนต้องเป็น 1-5';
    if (mb_strlen($d['review']) > 1000) $errors[] = 'รีวิวต้องไม่เกิน 1000 ตัวอักษร';
    if (!$errors) {
        $rating = $d['rating'] === '' ? null : (int)$d['rating'];
        db_exec("UPDATE library_items SET title = ?, author = ?, shelf = ?, is_owned = ?, read_status = ?, rating = ?, review = ?
                 WHERE id = ? AND user_id = ?",
                'sssisisii', [$d['title'], $d['author'], $d['shelf'] === '' ? null : $d['shelf'], $d['is_owned'], $d['read_status'],
                              $rating, $d['review'] === '' ? null : $d['review'], $id, $uid]);
        flash('บันทึกแล้ว');
        redirect('library.php');
    }
}

page_header('รีวิว/แก้ไขหนังสือ', 'library');
?>
<div class="card">
  <h1>รีวิว / แก้ไข</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-group"><label>ชื่อหนังสือ</label><input type="text" name="title" value="<?= e($d['title']) ?>" maxlength="150" required></div>
    <div class="form-group"><label>ผู้แต่ง</label><input type="text" name="author" value="<?= e($d['author']) ?>" maxlength="100"></div>
    <div class="form-group"><label>ชั้นหนังสือ</label><input type="text" name="shelf" value="<?= e($d['shelf']) ?>" maxlength="50"></div>
    <div class="form-group"><label>สถานะการอ่าน</label>
      <select name="read_status">
        <?php foreach (READ_STATUS as $k => $v): ?><option value="<?= e($k) ?>" <?= $d['read_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label class="check-row"><input type="checkbox" name="is_owned" value="1" <?= $d['is_owned'] ? 'checked' : '' ?>> เป็นหนังสือที่ฉันมีอยู่</label></div>
    <div class="form-group"><label>ให้คะแนน</label>
      <select name="rating">
        <option value="">ยังไม่ให้คะแนน</option>
        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $d['rating'] === (string)$i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?>
      </select></div>
    <div class="form-group"><label>รีวิว</label><textarea name="review" maxlength="1000"><?= e($d['review']) ?></textarea></div>
    <button class="btn" type="submit">บันทึก</button>
    <a href="library.php">ยกเลิก</a>
  </form>
</div>
<?php page_footer();
