<?php
/** book.php — รายละเอียดหนังสือ: สนใจ/ใส่ตะกร้า/เสนอแลก/ถามผู้ขาย + รีวิวจากสมาชิก */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();

$uid = (int)current_user_id();
$id  = get_int('id');
$b = db_one("SELECT books.*, users.full_name, users.id AS uid, categories.name AS category_name
             FROM books JOIN users ON books.owner_id = users.id
             LEFT JOIN categories ON books.category_id = categories.id
             WHERE books.id = ?", 'i', [$id]);
if (!$b) abort(404, 'ไม่พบหนังสือ');
$mine = (int)$b['owner_id'] === $uid;
// ประกาศที่ปิด/ขายแล้ว ให้เห็นเฉพาะเจ้าของ (หรือแอดมิน)
if ($b['status'] === 'closed' && !$mine && !is_admin()) abort(404, 'ไม่พบหนังสือ');

$isFav = (bool)db_one("SELECT 1 FROM favorites WHERE user_id = ? AND book_id = ?", 'ii', [$uid, $id]);
$inCart = (bool)db_one("SELECT 1 FROM cart_items WHERE user_id = ? AND book_id = ?", 'ii', [$uid, $id]);
$reviews = db_all("SELECT li.rating, li.review, u.full_name FROM library_items li JOIN users u ON u.id = li.user_id
                   WHERE li.title = ? AND li.review IS NOT NULL AND li.review <> '' ORDER BY li.id DESC LIMIT 5", 's', [$b['title']]);
$ret = 'book.php?id=' . $id;

page_header($b['title'], 'home');
?>
<div class="card">
  <div class="detail">
    <div>
      <?php if (!empty($b['cover'])): ?><img class="cover" src="uploads/<?= e($b['cover']) ?>" alt="ปก <?= e($b['title']) ?>">
      <?php else: ?><div class="cover cover-empty">📖</div><?php endif; ?>
    </div>
    <div>
      <h1><?= e($b['title']) ?></h1>
      <p class="muted">โดย <?= e($b['author']) ?><?= $b['category_name'] ? ' · หมวด ' . e($b['category_name']) : '' ?></p>
      <p>
        <span class="pill pill-<?= e($b['purpose']) ?>"><?= e(PURPOSES[$b['purpose']]) ?></span>
        <span class="pill"><?= e(CONDITIONS[$b['book_condition']]) ?></span>
        <span class="pill"><?= e(BOOK_STATUS[$b['status']]) ?></span>
      </p>
      <p class="price price-lg"><?= $b['purpose'] === 'sale' ? e(money($b['price'])) : 'เสนอแลกเปลี่ยน' ?></p>
      <p><?= nl2br(e($b['description'])) ?></p>
      <p class="muted">ผู้ลงประกาศ: <a href="profile.php?id=<?= (int)$b['owner_id'] ?>"><?= e($b['full_name']) ?></a> · ลงเมื่อ <?= e(fmt_date($b['created_at'])) ?></p>

      <?php if ($mine): ?>
        <div class="row-actions">
          <a class="btn btn-sm" href="edit.php?id=<?= (int)$b['id'] ?>">แก้ไข</a>
          <form method="post" action="delete.php" class="inline" data-confirm="ยืนยันลบประกาศนี้?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form>
        </div>
      <?php elseif ($b['status'] === 'available'): ?>
        <div class="row-actions">
          <form method="post" action="favorite.php" class="inline">
            <?= csrf_field() ?><input type="hidden" name="book_id" value="<?= $id ?>"><?= return_field($ret) ?>
            <button class="btn btn-sm btn-ghost" type="submit"><?= $isFav ? '♥ เลิกสนใจ' : '♡ บันทึกที่สนใจ' ?></button></form>
          <?php if ($b['purpose'] === 'sale'): ?>
            <?php if ($inCart): ?>
              <a class="btn btn-sm" href="cart.php">อยู่ในตะกร้าแล้ว →</a>
            <?php else: ?>
              <form method="post" action="cart.php" class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="book_id" value="<?= $id ?>">
                <button class="btn btn-sm btn-ok" type="submit">🛒 เพิ่มลงตะกร้า</button></form>
            <?php endif; ?>
          <?php else: ?>
            <a class="btn btn-sm btn-ok" href="exchange_offer.php?book=<?= $id ?>">⇄ เสนอแลกเปลี่ยน</a>
          <?php endif; ?>
          <a class="btn btn-sm" href="chat.php?book=<?= $id ?>&with=<?= (int)$b['owner_id'] ?>">💬 แชตถามผู้ลงประกาศ</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h1>รีวิวจากสมาชิก</h1>
  <?php if (!$reviews): ?><p class="muted">ยังไม่มีรีวิวสำหรับหนังสือชื่อนี้</p><?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div class="notif">
      <?php if ($r['rating']): ?><span class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span><?php endif; ?>
      <b><?= e($r['full_name']) ?></b><br><?= nl2br(e($r['review'])) ?>
    </div>
  <?php endforeach; ?>
</div>
<?php page_footer();
