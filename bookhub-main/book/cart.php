<?php
/** cart.php — ตะกร้าหนังสือ: เพิ่ม/ลบ/ชำระเงิน (สร้างคำสั่งซื้อ) ราคาอ่านจากฐานข้อมูลเสมอ */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();
$errors = [];
$ship = ['name' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'add') {
        $bid = post_int('book_id');
        $b = db_one("SELECT id, title, owner_id, purpose, status FROM books WHERE id = ?", 'i', [$bid]);
        if (!$b || $b['purpose'] !== 'sale' || $b['status'] !== 'available') {
            flash('หนังสือเล่มนี้ไม่พร้อมขาย', 'error');
        } elseif ((int)$b['owner_id'] === $uid) {
            flash('ไม่สามารถซื้อหนังสือของตัวเองได้', 'error');
        } else {
            $n = db_exec("INSERT IGNORE INTO cart_items (user_id, book_id) VALUES (?, ?)", 'ii', [$uid, $bid]);
            if ($n > 0) notify($b['owner_id'], current_user()['full_name'] . ' เพิ่มหนังสือ "' . $b['title'] . '" ของคุณลงตะกร้า', 'book.php?id=' . $bid);
            flash('เพิ่มลงตะกร้าแล้ว');
        }
        redirect('cart.php');
    }

    if ($action === 'remove') {
        db_exec("DELETE FROM cart_items WHERE user_id = ? AND book_id = ?", 'ii', [$uid, post_int('book_id')]);
        flash('นำออกจากตะกร้าแล้ว');
        redirect('cart.php');
    }

    if ($action === 'checkout') {
        $ship = ['name' => post_str('ship_name'), 'phone' => post_str('ship_phone'), 'address' => post_str('ship_address')];
        if ($ship['name'] === '' || mb_strlen($ship['name']) > 100) $errors[] = 'กรุณากรอกชื่อผู้รับ';
        if (!preg_match('/^[0-9+\- ]{9,20}$/', $ship['phone'])) $errors[] = 'เบอร์โทรไม่ถูกต้อง';
        if ($ship['address'] === '' || mb_strlen($ship['address']) > 300) $errors[] = 'กรุณากรอกที่อยู่จัดส่ง (ไม่เกิน 300 ตัวอักษร)';

        if (!$errors) {
            global $conn;
            $conn->begin_transaction();
            try {
                // ล็อกแถวหนังสือในตะกร้าที่ยังพร้อมขาย ป้องกันซื้อซ้อนกัน
                $items = db_all("SELECT books.id, books.title, books.price, books.owner_id FROM cart_items
                                 JOIN books ON books.id = cart_items.book_id
                                 WHERE cart_items.user_id = ? AND books.status = 'available' AND books.purpose = 'sale' AND books.owner_id <> ?
                                 FOR UPDATE", 'ii', [$uid, $uid]);
                if (!$items) {
                    $conn->rollback();
                    $errors[] = 'ไม่มีสินค้าที่พร้อมซื้อในตะกร้า';
                } else {
                    $total = 0;
                    foreach ($items as $it) $total += (float)$it['price'];
                    db_exec("INSERT INTO orders (buyer_id, total, ship_name, ship_phone, ship_address) VALUES (?, ?, ?, ?, ?)",
                            'idsss', [$uid, $total, $ship['name'], $ship['phone'], $ship['address']]);
                    $oid = $conn->insert_id;
                    foreach ($items as $it) {
                        db_exec("INSERT INTO order_items (order_id, book_id, seller_id, title, price) VALUES (?, ?, ?, ?, ?)",
                                'iiisd', [$oid, $it['id'], $it['owner_id'], $it['title'], $it['price']]);
                        db_exec("UPDATE books SET status = 'sold' WHERE id = ?", 'i', [$it['id']]);
                        notify($it['owner_id'], current_user()['full_name'] . ' สั่งซื้อหนังสือ "' . $it['title'] . '" ของคุณแล้ว', 'profile.php');
                    }
                    db_exec("DELETE FROM cart_items WHERE user_id = ?", 'i', [$uid]);
                    $conn->commit();
                    flash('สั่งซื้อสำเร็จ รวม ' . money($total) . ' (ดูประวัติได้ที่โปรไฟล์)');
                    redirect('profile.php');
                }
            } catch (Throwable $ex) {
                $conn->rollback();
                throw $ex;
            }
        }
    }
}

$items = db_all("SELECT books.*, users.full_name FROM cart_items
                 JOIN books ON books.id = cart_items.book_id
                 JOIN users ON users.id = books.owner_id
                 WHERE cart_items.user_id = ? ORDER BY cart_items.created_at DESC", 'i', [$uid]);
$total = 0;
foreach ($items as $it) if ($it['status'] === 'available') $total += (float)$it['price'];

page_header('ตะกร้าหนังสือ', 'cart');
?>
<div class="card">
  <h1>ตะกร้าหนังสือ</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <?php if (!$items): ?>
    <p class="empty">ตะกร้าว่างเปล่า</p>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>หนังสือ</th><th>ผู้ขาย</th><th>ราคา</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><a href="book.php?id=<?= (int)$it['id'] ?>"><?= e($it['title']) ?></a>
          <?php if ($it['status'] !== 'available'): ?><span class="muted">(ไม่พร้อมขายแล้ว)</span><?php endif; ?></td>
        <td><?= e($it['full_name']) ?></td>
        <td><?= e(money($it['price'])) ?></td>
        <td><form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="remove"><input type="hidden" name="book_id" value="<?= (int)$it['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">นำออก</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p><b>รวม: <?= e(money($total)) ?></b></p>
</div>

<div class="card">
  <h1>ข้อมูลจัดส่ง</h1>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="checkout">
    <div class="form-group"><label>ชื่อผู้รับ</label><input type="text" name="ship_name" value="<?= e($ship['name']) ?>" maxlength="100" required></div>
    <div class="form-group"><label>เบอร์โทร</label><input type="text" name="ship_phone" value="<?= e($ship['phone']) ?>" maxlength="20" required></div>
    <div class="form-group"><label>ที่อยู่จัดส่ง</label><textarea name="ship_address" maxlength="300" required><?= e($ship['address']) ?></textarea></div>
    <button class="btn btn-ok" type="submit">ยืนยันสั่งซื้อ</button>
  </form>
  <?php endif; ?>
</div>
<?php page_footer();
