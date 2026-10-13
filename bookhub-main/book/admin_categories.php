<?php
/** admin_categories.php — จัดการหมวดหมู่หนังสือ (เพิ่ม/เปลี่ยนชื่อ/ลบ) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');
    $name = post_str('name');
    try {
        if ($action === 'add' || $action === 'rename') {
            if ($name === '' || mb_strlen($name) > 60) {
                flash('ชื่อหมวดหมู่ต้องมี 1-60 ตัวอักษร', 'error');
            } elseif ($action === 'add') {
                db_exec("INSERT INTO categories (name) VALUES (?)", 's', [$name]);
                flash('เพิ่มหมวดหมู่แล้ว');
            } else {
                db_exec("UPDATE categories SET name = ? WHERE id = ?", 'si', [$name, post_int('id')]);
                flash('เปลี่ยนชื่อแล้ว');
            }
        } elseif ($action === 'delete') {
            db_exec("DELETE FROM categories WHERE id = ?", 'i', [post_int('id')]);
            flash('ลบหมวดหมู่แล้ว (หนังสือในหมวดนี้จะไม่มีหมวด)');
        }
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() === 1062) flash('มีชื่อหมวดหมู่นี้อยู่แล้ว', 'error'); else throw $ex;
    }
    redirect('admin_categories.php');
}

$cats = db_all("SELECT c.id, c.name, COUNT(b.id) AS n FROM categories c LEFT JOIN books b ON b.category_id = c.id GROUP BY c.id, c.name ORDER BY c.name");

page_header('จัดการหมวดหมู่', 'admin');
admin_tabs('cats');
?>
<div class="card">
  <h1>เพิ่มหมวดหมู่</h1>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="form-group"><input type="text" name="name" maxlength="60" placeholder="ชื่อหมวดหมู่" required></div>
    <button class="btn" type="submit">+ เพิ่ม</button></form>
</div>
<div class="card">
  <h1>หมวดหมู่ทั้งหมด</h1>
  <div class="table-wrap"><table>
    <thead><tr><th>ชื่อ</th><th>จำนวนประกาศ</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="60" required>
          <button class="btn btn-sm btn-ghost" type="submit">บันทึกชื่อ</button></form></td>
        <td><?= (int)$c['n'] ?></td>
        <td><form method="post" class="inline" data-confirm="ลบหมวดหมู่นี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php page_footer();
