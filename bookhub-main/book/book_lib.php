<?php
/**
 * book_lib.php — ตัวช่วยฟอร์มประกาศหนังสือ (ใช้ร่วมกันใน create.php / edit.php)
 */
function book_input() {
    return [
        'title'          => post_str('title'),
        'author'         => post_str('author'),
        'category_id'    => post_int('category_id'),
        'book_condition' => post_str('book_condition'),
        'purpose'        => post_str('purpose'),
        'price'          => post_str('price'),
        'description'    => post_str('description'),
    ];
}

function book_validate(array $d, array $cats) {
    $err = [];
    if ($d['title'] === '' || mb_strlen($d['title']) > 150) $err[] = 'กรุณากรอกชื่อหนังสือ (ไม่เกิน 150 ตัวอักษร)';
    if ($d['author'] === '' || mb_strlen($d['author']) > 100) $err[] = 'กรุณากรอกชื่อผู้แต่ง (ไม่เกิน 100 ตัวอักษร)';
    if (!in_array($d['category_id'], array_map('intval', array_column($cats, 'id')), true)) $err[] = 'กรุณาเลือกหมวดหมู่';
    if (!isset(CONDITIONS[$d['book_condition']])) $err[] = 'กรุณาเลือกสภาพหนังสือ';
    if (!isset(PURPOSES[$d['purpose']])) $err[] = 'กรุณาเลือกประเภทประกาศ';
    if ($d['purpose'] === 'sale') {
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $d['price']) || (float)$d['price'] <= 0) $err[] = 'กรุณากรอกราคาเป็นตัวเลขมากกว่า 0';
    }
    if (mb_strlen($d['description']) > 1000) $err[] = 'รายละเอียดต้องไม่เกิน 1000 ตัวอักษร';
    return $err;
}

function book_form_fields(array $d, array $cats, $cover = null) {
    ?>
    <div class="form-group"><label>ชื่อหนังสือ</label>
      <input type="text" name="title" value="<?= e($d['title']) ?>" maxlength="150" required></div>
    <div class="form-group"><label>ผู้แต่ง</label>
      <input type="text" name="author" value="<?= e($d['author']) ?>" maxlength="100" required></div>
    <div class="form-group"><label>หมวดหมู่</label>
      <select name="category_id" required>
        <option value="">-- เลือกหมวดหมู่ --</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$d['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-group"><label>สภาพหนังสือ</label>
      <select name="book_condition" required>
        <?php foreach (CONDITIONS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $d['book_condition'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-group"><label>ประเภทประกาศ</label>
      <select name="purpose" required>
        <?php foreach (PURPOSES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $d['purpose'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-group"><label>ราคา (บาท) — กรอกเมื่อประเภท "ขาย"</label>
      <input type="text" inputmode="decimal" name="price" value="<?= e($d['price']) ?>" maxlength="10"></div>
    <div class="form-group"><label>รายละเอียด</label>
      <textarea name="description" maxlength="1000"><?= e($d['description']) ?></textarea></div>
    <div class="form-group"><label>รูปปกหนังสือ (JPG/PNG/GIF/WEBP ไม่เกิน 2MB)</label>
      <?php if ($cover): ?><img src="uploads/<?= e($cover) ?>" width="120" alt="ปกเดิม"><br><?php endif; ?>
      <input type="file" name="cover" accept=".jpg,.jpeg,.png,.gif,.webp,image/*"></div>
    <?php
}

function all_categories() {
    return db_all("SELECT id, name FROM categories ORDER BY name");
}
