<?php
/** admin.php — แดชบอร์ดผู้ดูแลระบบ (เฉพาะ role = admin ตรวจจากฐานข้อมูลทุกครั้ง) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_admin();

$s = [
    'ผู้ใช้ทั้งหมด'       => db_one("SELECT COUNT(*) c FROM users")['c'],
    'ประกาศที่เปิดอยู่'    => db_one("SELECT COUNT(*) c FROM books WHERE status = 'available'")['c'],
    'คำสั่งซื้อ'          => db_one("SELECT COUNT(*) c FROM orders")['c'],
    'การแลกที่สำเร็จ'      => db_one("SELECT COUNT(*) c FROM exchanges WHERE status = 'completed'")['c'],
    'การแลกที่รอ/กำลังแลก' => db_one("SELECT COUNT(*) c FROM exchanges WHERE status IN ('pending','accepted')")['c'],
    'รีวิวทั้งหมด'         => db_one("SELECT COUNT(*) c FROM library_items WHERE review IS NOT NULL AND review <> ''")['c'],
];
$latest = db_all("SELECT id, username, full_name, role, is_banned, created_at FROM users ORDER BY id DESC LIMIT 5");

page_header('แอดมิน', 'admin');
admin_tabs('dash');
?>
<div class="stat-grid">
  <?php foreach ($s as $label => $v): ?><div class="stat"><b><?= (int)$v ?></b><?= e($label) ?></div><?php endforeach; ?>
</div>
<div class="card">
  <h1>สมาชิกล่าสุด</h1>
  <div class="table-wrap"><table>
    <thead><tr><th>ชื่อผู้ใช้</th><th>ชื่อ</th><th>บทบาท</th><th>สมัครเมื่อ</th></tr></thead>
    <tbody>
    <?php foreach ($latest as $u): ?>
      <tr><td><?= e($u['username']) ?><?= $u['is_banned'] ? ' <span class="muted">(ถูกแบน)</span>' : '' ?></td><td><?= e($u['full_name']) ?></td><td><?= e($u['role']) ?></td><td><?= e(fmt_date($u['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php page_footer();
