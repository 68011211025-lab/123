<?php
/**
 * register.php — สมัครสมาชิก
 * - บทบาทถูกกำหนดเป็น 'user' ฝั่งเซิร์ฟเวอร์เสมอ (ไม่รับ role จากฟอร์ม)
 * - เก็บรหัสผ่านด้วย password_hash() (bcrypt) ไม่เก็บรหัสผ่านจริง
 */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

start_secure_session();
if (!empty($_SESSION['user_id'])) redirect('index.php');

$errors = [];
$username = $email = $full_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username  = post_str('username');
    $email     = post_str('email');
    $full_name = post_str('full_name');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';
    if (!is_string($password)) $password = '';
    if (!is_string($confirm)) $confirm = '';

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9 หรือ _ ยาว 3-30 ตัวอักษร';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) $errors[] = 'อีเมลไม่ถูกต้อง';
    if ($full_name === '' || mb_strlen($full_name) > 100) $errors[] = 'กรุณากรอกชื่อ-นามสกุล (ไม่เกิน 100 ตัวอักษร)';
    if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'รหัสผ่านต้องยาว 8-72 ตัวอักษร';
    elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) $errors[] = 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
    if ($password !== $confirm) $errors[] = 'ยืนยันรหัสผ่านไม่ตรงกัน';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            db_exec("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'user')",
                    'ssss', [$username, $email, $hash, $full_name]);
            flash('สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
            else throw $ex;
        }
    }
}

auth_header('สมัครสมาชิก');
?>
  <div class="card">
    <h1>สมัครสมาชิก BookHub</h1>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group"><label>ชื่อผู้ใช้</label>
        <input type="text" name="username" value="<?= e($username) ?>" maxlength="30" required autofocus></div>
      <div class="form-group"><label>อีเมล</label>
        <input type="email" name="email" value="<?= e($email) ?>" maxlength="120" required></div>
      <div class="form-group"><label>ชื่อ-นามสกุล</label>
        <input type="text" name="full_name" value="<?= e($full_name) ?>" maxlength="100" required></div>
      <div class="form-group"><label>รหัสผ่าน (อย่างน้อย 8 ตัว มีตัวอักษรและตัวเลข)</label>
        <input type="password" name="password" maxlength="72" required></div>
      <div class="form-group"><label>ยืนยันรหัสผ่าน</label>
        <input type="password" name="confirm" maxlength="72" required></div>
      <button class="btn" type="submit">สมัครสมาชิก</button>
      <a href="login.php">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </form>
  </div>
<?php auth_footer();
