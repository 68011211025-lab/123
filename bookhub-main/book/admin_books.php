<?php
/** admin_books.php — ตรวจสอบ/ลบประกาศหนังสือที่ไม่เหมาะสม (แอดมินลบได้ทุกประกาศ) */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = post_int('id');
    $b = db_one("SELECT id, title, cover, owner_id FROM books WHERE id = ?", 'i', [$id]);
    if (!$b) {
        flash('ไม่พบประกาศ', 'error');
    } else {
        // คลี่คลายรายการแลกที่ค้างอยู่ก่อนลบ: คืนสถานะหนังสืออีกเล่ม และแจ้งผู้เกี่ยวข้อง
        $exs = db_all("SELECT * FROM exchanges WHERE status IN ('pending','accepted') AND (target_book_id = ? OR offered_book_id = ?)", 'ii', [$id, $id]);
        foreach ($exs as $x) {
            if ($x['status'] === 'accepted') {
                db_exec("UPDATE books SET status = 'available' WHERE id IN (?, ?) AND id <> ? AND status = 'reserved'", 'iii', [$x['target_book_id'], $x['offered_book_id'], $id]);
            }
            notify($x['requester_id'], 'รายการแลกถูกยกเลิก เพราะแอดมินลบประกาศ "' . $b['title'] . '"', 'exchange.php');
            notify($x['owner_id'], 'รายการแลกถูกยกเลิก เพราะแอดมินลบประกาศ "' . $b['title'] . '"', 'exchange.php');
        }
        db_exec("DELETE FROM books WHERE id = ?", 'i', [$id]);
        delete_upload($b['cover']);
        notify($b['owner_id'], 'ประกาศ "' . $b['title'] . '" ของคุณถูกผู้ดูแลระบบลบ', 'my_books.php');
        flash('ลบประกาศแล้ว');
    }
    redirect('admin_books.php');
}

$rows = db_all("SELECT books.id, books.title, books.purpose, books.status, books.price, books.created_at, users.username
                FROM books JOIN users ON users.id = books.owner_id ORDER BY books.id DESC LIMIT 200");

page_header('จัดการประกาศ', 'admin');
admin_tabs('books');
?>
<div class="card">
  <h1>ประกาศหนังสือทั้งหมด</h1>
  <div class="table-wrap"><table>
    <thead><tr><th>ชื่อหนังสือ</th><th>เจ้าของ</th><th>ประเภท</th><th>ราคา</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="book.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
        <td><?= e($r['username']) ?></td>
        <td><?= e(PURPOSES[$r['purpose']]) ?></td>
        <td><?= $r['purpose'] === 'sale' ? e(money($r['price'])) : '-' ?></td>
        <td><?= e(BOOK_STATUS[$r['status']]) ?></td>
        <td><form method="post" class="inline" data-confirm="ลบประกาศนี้ถาวร?"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">ลบ</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php page_footer();
