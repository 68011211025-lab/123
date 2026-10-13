<?php
/** create.php — ลงประกาศหนังสือ (ขาย/แลกเปลี่ยน) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/book_lib.php';

start_secure_session();
require_login();

$errors = [];
$cats = all_categories();
$d = ['title' => '', 'author' => '', 'category_id' => 0, 'book_condition' => 'good', 'purpose' => 'sale', 'price' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = book_input();
    $errors = book_validate($d, $cats);

    $cover = null;
    if (!$errors) {
        $cover = safe_upload('cover');          // null = ไม่เลือกไฟล์, false = ไฟล์ไม่ถูกต้อง
        if ($cover === false) $errors[] = 'ไฟล์รูปไม่ถูกต้อง (ต้องเป็น JPG/PNG/GIF/WEBP ขนาดไม่เกิน 2MB)';
    }

    if (!$errors) {
        $price = $d['purpose'] === 'sale' ? $d['price'] : null;
        $owner_id = current_user_id();
        db_exec("INSERT INTO books (title, author, category_id, book_condition, purpose, price, description, cover, owner_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'ssisssssi', [$d['title'], $d['author'], $d['category_id'], $d['book_condition'], $d['purpose'],
                              $price, $d['description'], $cover, $owner_id]);
        flash('ลงประกาศเรียบร้อยแล้ว');
        redirect('my_books.php');
    }
}

page_header('ลงประกาศหนังสือ', 'mybooks');
?>
<div class="card">
  <h1>ลงประกาศหนังสือ</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php book_form_fields($d, $cats); ?>
    <button type="submit" class="btn">+ ลงประกาศ</button>
    <a href="my_books.php">ยกเลิก</a>
  </form>
</div>
<?php page_footer();
