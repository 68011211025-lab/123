<?php
/** notifications.php — แจ้งเตือน (มีคนเสนอแลก / สนใจหนังสือ / ข้อความใหม่ ฯลฯ) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$list = db_all("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100", 'i', [$uid]);
db_exec("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$uid]);

page_header('แจ้งเตือน', 'notif');
?>
<div class="card">
  <h1>แจ้งเตือน</h1>
  <?php if (!$list): ?><p class="empty">ยังไม่มีการแจ้งเตือน</p><?php endif; ?>
  <?php foreach ($list as $n):
      $link = preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $n['link']) ? $n['link'] : ''; ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
      <?php if ($link): ?><a href="<?= e($link) ?>"><?= e($n['message']) ?></a><?php else: ?><?= e($n['message']) ?><?php endif; ?>
      <div class="muted"><?= e(fmt_date($n['created_at'])) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php page_footer();
