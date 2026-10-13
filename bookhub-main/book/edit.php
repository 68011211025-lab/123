<?php
/** edit.php — แก้ไขประกาศ (เจ้าของเท่านั้น — ตรวจที่ฝั่งเซิร์ฟเวอร์ทั้งตอนดูและตอนบันทึก) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/book_lib.php';

start_secure_session();
require_login();

$id = get_int('id');
$book = db_one("SELECT * FROM books WHERE id = ?", 'i', [$id]);
if (!$book) abort(404, 'ไม่พบหนังสือ');
if ((int)$book['owner_id'] !== (int)current_user_id()) abort(403, 'คุณไม่มีสิทธิ์แก้ไขหนังสือเล่มนี้');
if (in_array($book['status'], ['reserved', 'sold'], true)) {
    flash('ไม่สามารถแก้ไขประกาศที่อยู่ระหว่างแลก หรือขายไปแล้ว', 'error');
    redirect('my_books.php');
}

$errors = [];
$cats = all_categories();
$d = [
    'title' => $book['title'], 'author' => $book['author'], 'category_id' => (int)$book['category_id'],
    'book_condition' => $book['book_condition'], 'purpose' => $book['purpose'],
    'price' => $book['price'] === null ? '' : (string)$book['price'], 'description' => (string)$book['description'],
];
$status = $book['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = book_input();
    $status = post_str('status');
    $errors = book_validate($d, $cats);
    if (!in_array($status, ['available', 'closed'], true)) $errors[] = 'สถานะไม่ถูกต้อง';

    $cover = $book['cover'];
    $newCover = null;
    if (!$errors) {
        $newCover = safe_upload('cover');
        if ($newCover === false) $errors[] = 'ไฟล์รูปไม่ถูกต้อง (ต้องเป็น JPG/PNG/GIF/WEBP ขนาดไม่เกิน 2MB)';
        elseif ($newCover !== null) $cover = $newCover;     // ไม่เลือกรูปใหม่ = ใช้รูปเดิม
    }

    if (!$errors) {
        $price = $d['purpose'] === 'sale' ? $d['price'] : null;
        $owner_id = current_user_id();
        $n = db_exec("UPDATE books SET title = ?, author = ?, category_id = ?, book_condition = ?, purpose = ?,
                      price = ?, description = ?, cover = ?, status = ?
                      WHERE id = ? AND owner_id = ? AND status IN ('available','closed')",
                     'ssissssssii', [$d['title'], $d['author'], $d['category_id'], $d['book_condition'], $d['purpose'],
                                     $price, $d['description'], $cover, $status, $id, $owner_id]);
        if ($newCover !== null && $book['cover']) delete_upload($book['cover']);
        flash('บันทึกการแก้ไขแล้ว');
        redirect('my_books.php');
    }
}

page_header('แก้ไขประกาศ', 'mybooks');
?>
<div class="card">
  <h1>แก้ไขประกาศ</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php book_form_fields($d, $cats, $book['cover']); ?>
    <div class="form-group"><label>สถานะประกาศ</label>
      <select name="status">
        <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>เปิดประกาศ</option>
        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>ปิดประกาศ (ซ่อน)</option>
      </select></div>
    <button type="submit" class="btn">บันทึก</button>
    <a href="my_books.php">ยกเลิก</a>
  </form>
</div>
<?php page_footer();
