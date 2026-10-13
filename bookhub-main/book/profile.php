<?php
/**
 * profile.php — โปรไฟล์ผู้ใช้
 *  - ?id=ผู้อื่น : ดูโปรไฟล์สาธารณะ (ชื่อ, bio, หนังสือที่ลงขาย/แลก) ไม่แสดงอีเมล
 *  - ของตัวเอง   : แก้ไขโปรไฟล์ + เปลี่ยนรหัสผ่าน + ประวัติการซื้อ/ขาย/แลก
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$me = current_user();
$uid = (int)$me['id'];
$viewId = get_int('id') ?: $uid;

/* ---------- โปรไฟล์ของคนอื่น ---------- */
if ($viewId !== $uid) {
    $u = db_one("SELECT id, full_name, bio, created_at FROM users WHERE id = ? AND is_banned = 0", 'i', [$viewId]);
    if (!$u) abort(404, 'ไม่พบผู้ใช้');
    $books = db_all("SELECT books.*, users.full_name, categories.name AS category_name FROM books
                     JOIN users ON users.id = books.owner_id LEFT JOIN categories ON categories.id = books.category_id
                     WHERE books.owner_id = ? AND books.status = 'available' ORDER BY books.id DESC", 'i', [$viewId]);
    page_header($u['full_name'], '');
    ?>
    <div class="card">
      <h1><?= e($u['full_name']) ?></h1>
      <p><?= nl2br(e($u['bio'] ?? '')) ?></p>
      <p class="muted">เป็นสมาชิกตั้งแต่ <?= e(fmt_date($u['created_at'])) ?></p>
    </div>
    <div class="card"><h1>หนังสือที่ลงประกาศ</h1>
      <?php if (!$books): ?><p class="empty">ไม่มีประกาศที่เปิดอยู่</p>
      <?php else: ?><div class="grid"><?php foreach ($books as $r) book_card($r, $uid, 'profile.php?id=' . $viewId); ?></div><?php endif; ?>
    </div>
    <?php
    page_footer();
    exit;
}

/* ---------- โปรไฟล์ตัวเอง ---------- */
$errors = [];
$pwErrors = [];
$f = ['full_name' => $me['full_name'], 'email' => $me['email'], 'bio' => (string)$me['bio']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = post_str('form');

    if ($form === 'profile') {
        $f = ['full_name' => post_str('full_name'), 'email' => post_str('email'), 'bio' => post_str('bio')];
        if ($f['full_name'] === '' || mb_strlen($f['full_name']) > 100) $errors[] = 'กรุณากรอกชื่อ-นามสกุล (ไม่เกิน 100 ตัวอักษร)';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 120) $errors[] = 'อีเมลไม่ถูกต้อง';
        if (mb_strlen($f['bio']) > 300) $errors[] = 'แนะนำตัวต้องไม่เกิน 300 ตัวอักษร';
        if (!$errors) {
            try {
                db_exec("UPDATE users SET full_name = ?, email = ?, bio = ? WHERE id = ?", 'sssi',
                        [$f['full_name'], $f['email'], $f['bio'] === '' ? null : $f['bio'], $uid]);   // ไม่แตะ role/is_banned
                $_SESSION['full_name'] = $f['full_name'];
                flash('บันทึกโปรไฟล์แล้ว');
                redirect('profile.php');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) $errors[] = 'อีเมลนี้ถูกใช้แล้ว'; else throw $ex;
            }
        }
    } elseif ($form === 'password') {
        $cur = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
        $new = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
        $cfm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
        $row = db_one("SELECT password_hash FROM users WHERE id = ?", 'i', [$uid]);
        if (!password_verify($cur, $row['password_hash'])) $pwErrors[] = 'รหัสผ่านเดิมไม่ถูกต้อง';
        if (strlen($new) < 8 || strlen($new) > 72) $pwErrors[] = 'รหัสผ่านใหม่ต้องยาว 8-72 ตัวอักษร';
        elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $pwErrors[] = 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
        if ($new !== $cfm) $pwErrors[] = 'ยืนยันรหัสผ่านไม่ตรงกัน';
        if (!$pwErrors) {
            db_exec("UPDATE users SET password_hash = ? WHERE id = ?", 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('เปลี่ยนรหัสผ่านแล้ว');
            redirect('profile.php');
        }
    }
}

$orders = db_all("SELECT o.id, o.total, o.created_at, GROUP_CONCAT(oi.title SEPARATOR ', ') AS titles
                  FROM orders o JOIN order_items oi ON oi.order_id = o.id
                  WHERE o.buyer_id = ? GROUP BY o.id, o.total, o.created_at ORDER BY o.id DESC LIMIT 20", 'i', [$uid]);
$sold = db_all("SELECT oi.title, oi.price, o.created_at, u.full_name FROM order_items oi
                JOIN orders o ON o.id = oi.order_id JOIN users u ON u.id = o.buyer_id
                WHERE oi.seller_id = ? ORDER BY oi.id DESC LIMIT 20", 'i', [$uid]);
$swaps = db_all("SELECT e.status, e.updated_at, t.title AS target_title, o.title AS offered_title
                 FROM exchanges e JOIN books t ON t.id = e.target_book_id JOIN books o ON o.id = e.offered_book_id
                 WHERE (e.owner_id = ? OR e.requester_id = ?) AND e.status IN ('completed','accepted')
                 ORDER BY e.id DESC LIMIT 20", 'ii', [$uid, $uid]);

page_header('โปรไฟล์ของฉัน', '');
?>
<div class="card">
  <h1>โปรไฟล์ของฉัน</h1>
  <p class="muted">ชื่อผู้ใช้: <b><?= e($me['username']) ?></b> · บทบาท: <b><?= e($me['role']) ?></b></p>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="form" value="profile">
    <div class="form-group"><label>ชื่อ-นามสกุล</label><input type="text" name="full_name" value="<?= e($f['full_name']) ?>" maxlength="100" required></div>
    <div class="form-group"><label>อีเมล</label><input type="email" name="email" value="<?= e($f['email']) ?>" maxlength="120" required></div>
    <div class="form-group"><label>แนะนำตัว</label><textarea name="bio" maxlength="300"><?= e($f['bio']) ?></textarea></div>
    <button class="btn" type="submit">บันทึกโปรไฟล์</button>
  </form>
</div>

<div class="card">
  <h1>เปลี่ยนรหัสผ่าน</h1>
  <?php foreach ($pwErrors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="form" value="password">
    <div class="form-group"><label>รหัสผ่านเดิม</label><input type="password" name="current" maxlength="72" required></div>
    <div class="form-group"><label>รหัสผ่านใหม่</label><input type="password" name="new" maxlength="72" required></div>
    <div class="form-group"><label>ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm" maxlength="72" required></div>
    <button class="btn" type="submit">เปลี่ยนรหัสผ่าน</button>
  </form>
</div>

<div class="card">
  <h1>ประวัติการซื้อ</h1>
  <?php if (!$orders): ?><p class="empty">ยังไม่มีประวัติการซื้อ</p><?php endif; ?>
  <?php foreach ($orders as $o): ?>
    <div class="notif">#<?= (int)$o['id'] ?> — <?= e($o['titles']) ?> <b><?= e(money($o['total'])) ?></b><div class="muted"><?= e(fmt_date($o['created_at'])) ?></div></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <h1>ประวัติการขาย</h1>
  <?php if (!$sold): ?><p class="empty">ยังไม่มีรายการขาย</p><?php endif; ?>
  <?php foreach ($sold as $s): ?>
    <div class="notif"><?= e($s['title']) ?> — <?= e(money($s['price'])) ?> ให้ <?= e($s['full_name']) ?><div class="muted"><?= e(fmt_date($s['created_at'])) ?></div></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <h1>ประวัติการแลก</h1>
  <?php if (!$swaps): ?><p class="empty">ยังไม่มีรายการแลก</p><?php endif; ?>
  <?php foreach ($swaps as $s): ?>
    <div class="notif"><?= e($s['offered_title']) ?> ⇄ <?= e($s['target_title']) ?>
      <span class="status status-<?= e($s['status']) ?>"><?= e(EX_STATUS[$s['status']]) ?></span><div class="muted"><?= e(fmt_date($s['updated_at'])) ?></div></div>
  <?php endforeach; ?>
</div>
<?php page_footer();
