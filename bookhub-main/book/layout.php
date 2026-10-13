<?php
/**
 * layout.php — โครงหน้าเว็บร่วม (navbar, flash, การ์ดหนังสือ)
 */
function nav_link($href, $label, $key, $active, $badge = 0) {
    $cls = $key === $active ? ' class="active"' : '';
    $b = $badge > 0 ? ' <span class="badge-count">' . (int)$badge . '</span>' : '';
    return '<a href="' . e($href) . '"' . $cls . '>' . e($label) . $b . '</a>';
}

function page_header($title, $active = '') {
    $u = current_user();
    $nN = $nM = $nC = 0;
    if ($u) {
        $nN = (int)db_one("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$u['id']])['c'];
        $nM = (int)db_one("SELECT COUNT(*) c FROM messages WHERE receiver_id = ? AND is_read = 0", 'i', [$u['id']])['c'];
        $nC = (int)db_one("SELECT COUNT(*) c FROM cart_items WHERE user_id = ?", 'i', [$u['id']])['c'];
    }
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — BookHub</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <div class="brand"><a href="index.php">📚 BookHub</a></div>
    <?php if ($u): ?>
    <div class="nav-links">
        <?= nav_link('index.php', 'ตลาดหนังสือ', 'home', $active) ?>
        <?= nav_link('my_books.php', 'ประกาศของฉัน', 'mybooks', $active) ?>
        <?= nav_link('exchange.php', 'แลกเปลี่ยน', 'exchange', $active) ?>
        <?= nav_link('library.php', 'ห้องสมุดส่วนตัว', 'library', $active) ?>
        <?= nav_link('favorites.php', 'ที่สนใจ', 'fav', $active) ?>
        <?= nav_link('cart.php', 'ตะกร้า', 'cart', $active, $nC) ?>
        <?= nav_link('chat.php', 'แชต', 'chat', $active, $nM) ?>
        <?= nav_link('notifications.php', 'แจ้งเตือน', 'notif', $active, $nN) ?>
        <?= nav_link('recommend.php', 'แนะนำ', 'rec', $active) ?>
        <?php if ($u['role'] === 'admin'): ?>
            <?= nav_link('admin.php', 'แอดมิน', 'admin', $active) ?>
        <?php endif; ?>
    </div>
    <div class="user-info">
        <a href="profile.php"><b><?= e($u['full_name']) ?></b></a>
        <?php if ($u['role'] === 'admin'): ?><span class="role-tag">admin</span><?php endif; ?>
        <form method="post" action="logout.php" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="link-btn">ออกจากระบบ</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<div class="container">
<?php
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as [$type, $msg]) {
            echo '<div class="alert alert-' . ($type === 'error' ? 'error' : 'ok') . '">' . e($msg) . '</div>';
        }
        unset($_SESSION['flash']);
    }
}

function page_footer() {
    ?>
</div>
<script src="app.js"></script>
</body>
</html>
<?php
}

// หน้า login / register (ไม่มี navbar)
function auth_header($title) {
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — BookReadLoop </title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
<?php
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as [$type, $msg]) {
            echo '<div class="alert alert-' . ($type === 'error' ? 'error' : 'ok') . '">' . e($msg) . '</div>';
        }
        unset($_SESSION['flash']);
    }
}
function auth_footer() {
    echo '</div></body></html>';
}

// การ์ดหนังสือ 1 เล่ม (ใช้ในหน้าตลาด / ที่สนใจ / แนะนำ)
function book_card(array $b, $viewer_id, $return = 'index.php', $is_fav = false) {
    $mine = (int)$b['owner_id'] === (int)$viewer_id;
    ?>
<div class="book-card">
    <a href="book.php?id=<?= (int)$b['id'] ?>" class="cover-link">
        <?php if (!empty($b['cover'])): ?>
            <img class="cover" src="uploads/<?= e($b['cover']) ?>" alt="ปก <?= e($b['title']) ?>">
        <?php else: ?>
            <div class="cover cover-empty">📖</div>
        <?php endif; ?>
    </a>
    <div class="book-info">
        <a class="book-title" href="book.php?id=<?= (int)$b['id'] ?>"><?= e($b['title']) ?></a>
        <div class="muted"><?= e($b['author']) ?><?= !empty($b['category_name']) ? ' · ' . e($b['category_name']) : '' ?></div>
        <div>
            <span class="pill pill-<?= e($b['purpose']) ?>"><?= e(PURPOSES[$b['purpose']] ?? '') ?></span>
            <span class="pill"><?= e(CONDITIONS[$b['book_condition']] ?? '') ?></span>
        </div>
        <div class="price"><?= $b['purpose'] === 'sale' ? e(money($b['price'])) : 'เสนอแลกได้' ?></div>
        <div class="muted">โดย <?= e($b['full_name'] ?? '') ?><?php if ($mine): ?> <span class="owner-tag">ของคุณ</span><?php endif; ?></div>
        <?php if (!$mine): ?>
        <form method="post" action="favorite.php" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
            <?= return_field($return) ?>
            <button type="submit" class="btn btn-sm btn-ghost"><?= $is_fav ? '♥ เลิกสนใจ' : '♡ สนใจ' ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php
}

// แท็บเมนูของหน้าผู้ดูแลระบบ
function admin_tabs($active) {
    $tabs = ['admin.php' => ['dash', 'ภาพรวม'], 'admin_users.php' => ['users', 'ผู้ใช้'], 'admin_books.php' => ['books', 'ประกาศหนังสือ'],
             'admin_categories.php' => ['cats', 'หมวดหมู่'], 'admin_recommend.php' => ['rec', 'คำแนะนำหนังสือ']];
    echo '<div class="tabs">';
    foreach ($tabs as $href => [$key, $label]) {
        echo '<a href="' . e($href) . '" class="' . ($key === $active ? 'active' : '') . '">' . e($label) . '</a>';
    }
    echo '</div>';
}
