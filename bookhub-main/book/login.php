<?php
/**
 * login.php — เข้าสู่ระบบ
 * - prepared statement + password_verify
 * - session_regenerate_id(true) เมื่อล็อกอินสำเร็จ (กัน session fixation)
 * - จำกัดจำนวนครั้งที่ล็อกอินผิด (กัน brute force) และข้อความ error ไม่บอกว่าผิดที่ชื่อหรือรหัส
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_str('username');
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) $password = '';
    $ip = client_ip();

    // ล็อกอินผิดเกิน 5 ครั้ง/ชื่อผู้ใช้ หรือ 20 ครั้ง/IP ภายใน 15 นาที -> รอก่อน
    $byUser = (int)db_one("SELECT COUNT(*) c FROM login_attempts WHERE username = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)", 's', [mb_substr($username, 0, 50)])['c'];
    $byIp   = (int)db_one("SELECT COUNT(*) c FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)", 's', [$ip])['c'];

    if ($byUser >= 5 || $byIp >= 20) {
        $error = 'ล็อกอินผิดหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่';
    } else {
        $user = db_one("SELECT id, username, password_hash, full_name, is_banned FROM users WHERE username = ?", 's', [$username]);

        if ($user && !$user['is_banned'] && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            db_exec("DELETE FROM login_attempts WHERE username = ?", 's', [$username]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db_exec("UPDATE users SET password_hash = ? WHERE id = ?", 'si', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            header('Location: index.php');
            exit;
        } else {
            db_exec("INSERT INTO login_attempts (ip, username) VALUES (?, ?)", 'ss', [$ip, mb_substr($username, 0, 50)]);
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

auth_header('เข้าสู่ระบบ');
?>
  <div class="card">
    <h1>📚 BookHub — เข้าสู่ระบบ</h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>ชื่อผู้ใช้</label>
        <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" maxlength="50" required autofocus>
      </div>
      <div class="form-group">
        <label>รหัสผ่าน</label>
        <input type="password" name="password" maxlength="72" required>
      </div>
      <button class="btn" type="submit">เข้าสู่ระบบ</button>
      <a href="register.php">สมัครสมาชิก</a>
    </form>
    
  </div>
<?php auth_footer();
