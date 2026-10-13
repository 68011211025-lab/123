<?php
/** my_books.php — ประกาศของฉัน (แก้ไข/ลบได้เฉพาะของตัวเอง) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$rows = db_all("SELECT books.*, categories.name AS category_name FROM books
                LEFT JOIN categories ON categories.id = books.category_id
                WHERE books.owner_id = ? ORDER BY books.id DESC", 'i', [$uid]);

page_header('ประกาศของฉัน', 'mybooks');
?>
<div class="card">
  <h1>ประกาศของฉัน</h1>
  <a class="btn btn-add" href="create.php">+ ลงประกาศหนังสือ</a>
  <?php if (!$rows): ?>
    <p class="empty">คุณยังไม่ได้ลงประกาศ</p>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>ปก</th><th>ชื่อหนังสือ</th><th>ประเภท</th><th>ราคา</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php if ($r['cover']): ?><img class="thumb" src="uploads/<?= e($r['cover']) ?>" alt="ปก"><?php else: ?><span class="muted">ไม่มีรูป</span><?php endif; ?></td>
        <td><a href="book.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><div class="muted"><?= e($r['author']) ?> · <?= e(CONDITIONS[$r['book_condition']]) ?></div></td>
        <td><?= e(PURPOSES[$r['purpose']]) ?></td>
        <td><?= $r['purpose'] === 'sale' ? e(money($r['price'])) : '-' ?></td>
        <td><?= e(BOOK_STATUS[$r['status']]) ?></td>
        <td class="row-actions">
          <?php if (in_array($r['status'], ['available', 'closed'], true)): ?>
            <a class="btn btn-sm" href="edit.php?id=<?= (int)$r['id'] ?>">แก้ไข</a>
          <?php endif; ?>
          <form method="post" action="delete.php" class="inline" data-confirm="ยืนยันลบประกาศนี้?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php page_footer();
