<?php
/** recommend.php — แนะนำหนังสือ: จากแอดมิน, ตามหมวดที่คุณสนใจ, และหนังสือคะแนนรีวิวสูง */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$byAdmin = db_all("SELECT r.*, u.full_name FROM recommendations r JOIN users u ON u.id = r.admin_id ORDER BY r.id DESC LIMIT 10");

// แนะนำตามหมวดหมู่ของหนังสือที่เราบันทึกไว้ (ไม่รวมของตัวเองและที่บันทึกไปแล้ว)
$forYou = db_all("SELECT books.*, users.full_name, categories.name AS category_name
                  FROM books JOIN users ON users.id = books.owner_id
                  LEFT JOIN categories ON categories.id = books.category_id
                  WHERE books.status = 'available' AND books.owner_id <> ?
                    AND books.category_id IN (SELECT b2.category_id FROM favorites f JOIN books b2 ON b2.id = f.book_id WHERE f.user_id = ? AND b2.category_id IS NOT NULL)
                    AND books.id NOT IN (SELECT book_id FROM favorites WHERE user_id = ?)
                  ORDER BY books.id DESC LIMIT 8", 'iii', [$uid, $uid, $uid]);
if (!$forYou) {
    $forYou = db_all("SELECT books.*, users.full_name, categories.name AS category_name
                      FROM books JOIN users ON users.id = books.owner_id
                      LEFT JOIN categories ON categories.id = books.category_id
                      WHERE books.status = 'available' AND books.owner_id <> ?
                      ORDER BY books.id DESC LIMIT 8", 'i', [$uid]);
    $forYouNote = 'หนังสือมาใหม่ (บันทึกหนังสือที่สนใจเพื่อรับคำแนะนำตามหมวดหมู่)';
} else {
    $forYouNote = 'ตามหมวดหมู่ของหนังสือที่คุณสนใจ';
}
$favIds = array_column(db_all("SELECT book_id FROM favorites WHERE user_id = ?", 'i', [$uid]), 'book_id');

$top = db_all("SELECT title, author, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS cnt
               FROM library_items WHERE rating IS NOT NULL GROUP BY title, author
               ORDER BY avg_rating DESC, cnt DESC LIMIT 10");

page_header('แนะนำหนังสือ', 'rec');
?>
<div class="card">
  <h1>คำแนะนำจากบรรณารักษ์ (แอดมิน)</h1>
  <?php if (!$byAdmin): ?><p class="empty">ยังไม่มีคำแนะนำ</p><?php endif; ?>
  <?php foreach ($byAdmin as $r): ?>
    <div class="notif"><b><?= e($r['title']) ?></b> <span class="muted">— <?= e($r['author']) ?></span><br><?= nl2br(e($r['reason'])) ?>
      <div class="muted">แนะนำโดย <?= e($r['full_name']) ?> · <a href="index.php?q=<?= e(urlencode($r['title'])) ?>">ค้นหาในตลาด</a></div></div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h1>แนะนำสำหรับคุณ</h1>
  <p class="muted"><?= e($forYouNote) ?></p>
  <?php if (!$forYou): ?><p class="empty">ยังไม่มีหนังสือแนะนำ</p>
  <?php else: ?><div class="grid"><?php foreach ($forYou as $r) book_card($r, $uid, 'recommend.php', in_array($r['id'], $favIds)); ?></div><?php endif; ?>
</div>

<div class="card">
  <h1>หนังสือคะแนนรีวิวสูง</h1>
  <?php if (!$top): ?><p class="empty">ยังไม่มีรีวิว</p><?php endif; ?>
  <?php foreach ($top as $t): ?>
    <div class="notif"><span class="stars">★ <?= e($t['avg_rating']) ?></span> <b><?= e($t['title']) ?></b>
      <span class="muted">— <?= e($t['author']) ?> (<?= (int)$t['cnt'] ?> รีวิว)</span></div>
  <?php endforeach; ?>
</div>
<?php page_footer();
