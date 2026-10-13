<?php
/** favorites.php — รายการหนังสือที่สนใจ */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$rows = db_all("SELECT books.*, users.full_name, categories.name AS category_name
                FROM favorites JOIN books ON books.id = favorites.book_id
                JOIN users ON users.id = books.owner_id
                LEFT JOIN categories ON categories.id = books.category_id
                WHERE favorites.user_id = ? AND books.status <> 'closed'
                ORDER BY favorites.created_at DESC", 'i', [$uid]);

page_header('หนังสือที่สนใจ', 'fav');
?>
<div class="card">
  <h1>หนังสือที่สนใจ</h1>
  <?php if (!$rows): ?><p class="empty">ยังไม่มีรายการที่สนใจ</p>
  <?php else: ?>
    <div class="grid"><?php foreach ($rows as $r) book_card($r, $uid, 'favorites.php', true); ?></div>
  <?php endif; ?>
</div>
<?php page_footer();
