
<?php
/**
 * admin_users.php — จัดการสมาชิกอย่างปลอดภัย
 * - ตรวจสิทธิ์แอดมินจากฐานข้อมูล
 * - POST + CSRF สำหรับทุกคำสั่งเปลี่ยนแปลง
 * - ป้องกันจัดการบัญชีตัวเอง
 * - ป้องกันลดสิทธิ์แอดมินคนสุดท้าย
 * - ใช้ Prepared Statements
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_admin();

$me = (int) current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $id = post_int('id');
    $action = post_str('action');

    if ($id <= 0) {
        flash('รหัสผู้ใช้ไม่ถูกต้อง', 'error');
        redirect('admin_users.php');
    }

    $target = db_one(
        "SELECT id, username, role, is_banned
         FROM users WHERE id = ?",
        'i',
        [$id]
    );

    if (!$target) {
        flash('ไม่พบผู้ใช้', 'error');
        redirect('admin_users.php');
    }

    if ($id === $me) {
        flash('ไม่สามารถจัดการบัญชีของตัวเองได้', 'error');
        redirect('admin_users.php');
    }

    if ($action === 'set_role') {
        $role = post_str('role');

        if (!in_array($role, ['user', 'admin'], true)) {
            flash('บทบาทไม่ถูกต้อง', 'error');
            redirect('admin_users.php');
        }

        if ($target['role'] === $role) {
            flash('ผู้ใช้มีบทบาทนี้อยู่แล้ว', 'error');
            redirect('admin_users.php');
        }

        // ป้องกันลดสิทธิ์แอดมินคนสุดท้าย
        if ($target['role'] === 'admin' && $role === 'user') {
            $countRow = db_one(
                "SELECT COUNT(*) AS c FROM users WHERE role = 'admin'"
            );
            $adminCount = (int) ($countRow['c'] ?? 0);

            if ($adminCount <= 1) {
                flash('ระบบต้องมีแอดมินอย่างน้อย 1 บัญชี', 'error');
                redirect('admin_users.php');
            }
        }

        $changed = db_exec(
            "UPDATE users SET role = ?
             WHERE id = ? AND role = ?",
            'sis',
            [$role, $id, $target['role']]
        );

        if ($changed === 1) {
            flash(
                'เปลี่ยนบทบาทของ ' .
                $target['username'] . ' เป็น ' . $role . ' แล้ว'
            );
        } else {
            flash('ไม่สามารถเปลี่ยนบทบาทได้ กรุณาลองใหม่', 'error');
        }

    } elseif ($action === 'ban' || $action === 'unban') {
        $banned = ($action === 'ban') ? 1 : 0;

        if ((int) $target['is_banned'] === $banned) {
            flash('สถานะผู้ใช้เป็นสถานะนี้อยู่แล้ว', 'error');
            redirect('admin_users.php');
        }

        $changed = db_exec(
            "UPDATE users SET is_banned = ?
             WHERE id = ? AND is_banned = ?",
            'iii',
            [$banned, $id, (int) $target['is_banned']]
        );

        if ($changed === 1) {
            flash(
                ($banned ? 'แบน ' : 'ปลดแบน ') .
                $target['username'] . ' สำเร็จ'
            );
        } else {
            flash('ไม่สามารถเปลี่ยนสถานะได้ กรุณาลองใหม่', 'error');
        }

    } else {
        flash('คำสั่งไม่ถูกต้อง', 'error');
    }

    redirect('admin_users.php');
}

$users = db_all(
    "SELECT id, username, email, full_name, role,
            is_banned, created_at
     FROM users
     ORDER BY id"
);

page_header('จัดการผู้ใช้', 'admin');
admin_tabs('users');
?>

<div class="card">
  <h1>จัดการผู้ใช้</h1>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ผู้ใช้</th>
          <th>อีเมล</th>
          <th>บทบาท</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <b><?= e($u['username']) ?></b>
            <div class="muted"><?= e($u['full_name']) ?></div>
          </td>

          <td><?= e($u['email']) ?></td>

          <td><?= e($u['role']) ?></td>

          <td>
            <?php if ((int) $u['is_banned'] === 1): ?>
              <span class="status status-rejected">ถูกแบน</span>
            <?php else: ?>
              <span class="status status-completed">ปกติ</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ((int) $u['id'] === $me): ?>
              <span class="muted">(บัญชีของคุณ)</span>
            <?php else: ?>
              <div class="row-actions">

                <form method="post" class="inline"
                      data-confirm="ยืนยันการเปลี่ยนบทบาทผู้ใช้นี้?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id"
                         value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="role"
                         value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">

                  <button class="btn btn-sm btn-ghost" type="submit">
                    <?= $u['role'] === 'admin'
                        ? 'ลดเป็น user'
                        : 'เลื่อนเป็น admin' ?>
                  </button>
                </form>

                <form method="post" class="inline"
                      data-confirm="<?= (int) $u['is_banned'] === 1
                          ? 'ยืนยันปลดแบนผู้ใช้นี้?'
                          : 'ยืนยันแบนผู้ใช้นี้?' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id"
                         value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="action"
                         value="<?= (int) $u['is_banned'] === 1
                             ? 'unban'
                             : 'ban' ?>">

                  <button
                    class="btn btn-sm <?= (int) $u['is_banned'] === 1
                        ? 'btn-ok'
                        : 'btn-danger' ?>"
                    type="submit">
                    <?= (int) $u['is_banned'] === 1
                        ? 'ปลดแบน'
                        : 'แบน' ?>
                  </button>
                </form>

              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php page_footer(); ?>
