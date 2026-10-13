<?php
/** exchange.php — ติดตามสถานะการแลกเปลี่ยน: ข้อเสนอที่ได้รับ / ข้อเสนอที่ส่ง */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();
$uid = (int)current_user_id();

$sql = "SELECT e.*, t.title AS target_title, o.title AS offered_title,
               ru.full_name AS requester_name, ow.full_name AS owner_name
        FROM exchanges e
        JOIN books t ON t.id = e.target_book_id
        JOIN books o ON o.id = e.offered_book_id
        JOIN users ru ON ru.id = e.requester_id
        JOIN users ow ON ow.id = e.owner_id
        WHERE %s = ? ORDER BY e.id DESC";
$incoming = db_all(sprintf($sql, 'e.owner_id'), 'i', [$uid]);
$outgoing = db_all(sprintf($sql, 'e.requester_id'), 'i', [$uid]);

function ex_btn($id, $action, $label, $cls, $confirm = '') {
    $c = $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '';
    return '<form method="post" action="exchange_action.php" class="inline"' . $c . '>' . csrf_field() .
           '<input type="hidden" name="id" value="' . (int)$id . '"><input type="hidden" name="action" value="' . e($action) . '">' .
           '<button class="btn btn-sm ' . $cls . '" type="submit">' . e($label) . '</button></form> ';
}
function ex_table(array $rows, $uid, $mode) {
    if (!$rows) { echo '<p class="empty">ไม่มีรายการ</p>'; return; }
    ?>
    <div class="table-wrap"><table>
      <thead><tr><th>หนังสือ</th><th><?= $mode === 'in' ? 'ผู้เสนอ' : 'เจ้าของ' ?></th><th>สถานะ</th><th>จัดการ</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
          $isOwner = (int)$r['owner_id'] === $uid;
          $myConfirmed = $isOwner ? $r['owner_confirmed'] : $r['requester_confirmed'];
          ?>
        <tr>
          <td><b><?= e($r['offered_title']) ?></b> ⇄ <b><?= e($r['target_title']) ?></b>
            <?php if ($r['message']): ?><div class="muted">"<?= e($r['message']) ?>"</div><?php endif; ?>
            <div class="muted"><?= e(fmt_date($r['created_at'])) ?></div></td>
          <td><?= e($mode === 'in' ? $r['requester_name'] : $r['owner_name']) ?></td>
          <td><span class="status status-<?= e($r['status']) ?>"><?= e(EX_STATUS[$r['status']]) ?></span></td>
          <td>
            <?php if ($r['status'] === 'pending' && $isOwner): ?>
              <?= ex_btn($r['id'], 'accept', 'ยอมรับ', 'btn-ok') ?>
              <?= ex_btn($r['id'], 'reject', 'ปฏิเสธ', 'btn-danger', 'ปฏิเสธข้อเสนอนี้?') ?>
            <?php elseif ($r['status'] === 'pending'): ?>
              <?= ex_btn($r['id'], 'cancel', 'ยกเลิกข้อเสนอ', 'btn-ghost', 'ยกเลิกข้อเสนอนี้?') ?>
            <?php elseif ($r['status'] === 'accepted'): ?>
              <?php if ($myConfirmed): ?><span class="muted">คุณยืนยันแล้ว รออีกฝ่าย</span>
              <?php else: ?><?= ex_btn($r['id'], 'confirm', 'ยืนยันว่าแลกเสร็จแล้ว', 'btn-ok', 'ยืนยันว่าได้รับหนังสือแล้ว?') ?><?php endif; ?>
              <?= ex_btn($r['id'], 'cancel', 'ยกเลิก', 'btn-ghost', 'ยกเลิกการแลกนี้?') ?>
            <?php else: ?><span class="muted">-</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php
}

page_header('แลกเปลี่ยนหนังสือ', 'exchange');
?>
<div class="card">
  <h1>ข้อเสนอแลกที่ได้รับ (หนังสือของฉัน)</h1>
  <?php ex_table($incoming, $uid, 'in'); ?>
</div>
<div class="card">
  <h1>ข้อเสนอแลกที่ฉันส่ง</h1>
  <?php ex_table($outgoing, $uid, 'out'); ?>
  <p class="muted">เลือกหนังสือที่เปิดแลกได้จาก <a href="index.php?purpose=exchange">ตลาดหนังสือ (ประเภทแลกเปลี่ยน)</a></p>
</div>
<?php page_footer();
