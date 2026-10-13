<?php
/** library.php — ห้องสมุดส่วนตัว: เพิ่มหนังสือเข้าชั้น, อ่านแล้ว/อยากอ่าน, (รีวิวแก้ที่ library_edit.php) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();
$errors = [];
$d = ['title' => '', 'author' => '', 'shelf' => '', 'is_owned' => 1, 'read_status' => 'none'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = [
        'title' => post_str('title'), 'author' => post_str('author'), 'shelf' => post_str('shelf'),
        'is_owned' => isset($_POST['is_owned']) ? 1 : 0, 'read_status' => post_str('read_status'),
    ];
    if ($d['title'] === '' || mb_strlen($d['title']) > 150) $errors[] = 'กรุณากรอกชื่อหนังสือ (ไม่เกิน 150 ตัวอักษร)';
    if (mb_strlen($d['author']) > 100) $errors[] = 'ชื่อผู้แต่งต้องไม่เกิน 100 ตัวอักษร';
    if (mb_strlen($d['shelf']) > 50) $errors[] = 'ชื่อชั้นต้องไม่เกิน 50 ตัวอักษร';
    if (!isset(READ_STATUS[$d['read_status']])) $errors[] = 'สถานะการอ่านไม่ถูกต้อง';
    if (!$errors) {
        db_exec("INSERT INTO library_items (user_id, title, author, shelf, is_owned, read_status) VALUES (?, ?, ?, ?, ?, ?)",
                'isssis', [$uid, $d['title'], $d['author'], $d['shelf'] === '' ? null : $d['shelf'], $d['is_owned'], $d['read_status']]);
        flash('เพิ่มหนังสือเข้าห้องสมุดแล้ว');
        redirect('library.php');
    }
}

$filter = get_str('show');
$where = 'user_id = ?';
if ($filter === 'owned') $where .= ' AND is_owned = 1';
elseif ($filter === 'read') $where .= " AND read_status = 'read'";
elseif ($filter === 'want') $where .= " AND read_status = 'want'";
else $filter = '';
$items = db_all("SELECT * FROM library_items WHERE $where ORDER BY shelf IS NULL, shelf, id DESC", 'i', [$uid]);

page_header('ห้องสมุดส่วนตัว', 'library');
?>
<div class="card">
  <h1>เพิ่มหนังสือเข้าชั้นหนังสือ</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group"><label>ชื่อหนังสือ</label><input type="text" name="title" value="<?= e($d['title']) ?>" maxlength="150" required></div>
    <div class="form-group"><label>ผู้แต่ง</label><input type="text" name="author" value="<?= e($d['author']) ?>" maxlength="100"></div>
    <div class="form-group"><label>ชั้นหนังสือ (เช่น นิยาย, วิชาการ)</label><input type="text" name="shelf" value="<?= e($d['shelf']) ?>" maxlength="50"></div>
    <div class="form-group"><label>สถานะการอ่าน</label>
      <select name="read_status">
        <?php foreach (READ_STATUS as $k => $v): ?><option value="<?= e($k) ?>" <?= $d['read_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label class="check-row"><input type="checkbox" name="is_owned" value="1" <?= $d['is_owned'] ? 'checked' : '' ?>> เป็นหนังสือที่ฉันมีอยู่</label></div>
    <button class="btn" type="submit">+ เพิ่มเข้าชั้น</button>
  </form>
</div>

<div class="card">
  <h1>ห้องสมุดของฉัน</h1>
  <div class="tabs">
    <a href="library.php" class="<?= $filter === '' ? 'active' : '' ?>">ทั้งหมด</a>
    <a href="library.php?show=owned" class="<?= $filter === 'owned' ? 'active' : '' ?>">หนังสือที่มี</a>
    <a href="library.php?show=read" class="<?= $filter === 'read' ? 'active' : '' ?>">อ่านแล้ว</a>
    <a href="library.php?show=want" class="<?= $filter === 'want' ? 'active' : '' ?>">อยากอ่าน</a>
  </div>
  <?php if (!$items): ?><p class="empty">ยังไม่มีหนังสือในรายการนี้</p>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>หนังสือ</th><th>ชั้น</th><th>สถานะ</th><th>คะแนน / รีวิว</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><b><?= e($it['title']) ?></b><div class="muted"><?= e($it['author']) ?></div></td>
        <td><?= e($it['shelf'] ?? '-') ?></td>
        <td><?= e(READ_STATUS[$it['read_status']]) ?><?= $it['is_owned'] ? ' · มีเล่มนี้' : '' ?></td>
        <td><?php if ($it['rating']): ?><span class="stars"><?= str_repeat('★', (int)$it['rating']) . str_repeat('☆', 5 - (int)$it['rating']) ?></span><?php endif; ?>
            <?php if ($it['review']): ?><div class="muted"><?= e(mb_substr($it['review'], 0, 80)) ?><?= mb_strlen($it['review']) > 80 ? '…' : '' ?></div><?php endif; ?></td>
        <td class="row-actions">
          <a class="btn btn-sm" href="library_edit.php?id=<?= (int)$it['id'] ?>">รีวิว/แก้ไข</a>
          <form method="post" action="library_delete.php" class="inline" data-confirm="ลบรายการนี้ออกจากห้องสมุด?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php page_footer();
