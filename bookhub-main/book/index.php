<?php
/** index.php — ตลาดหนังสือ: ค้นหาตามชื่อ/ผู้แต่ง/หมวดหมู่ เรียงใหม่ไปเก่า */
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/book_lib.php';
require __DIR__ . '/layout.php';

start_secure_session();
require_login();

$uid = (int)current_user_id();
$q        = mb_substr(get_str('q'), 0, 100);
$cat      = get_int('category');
$purpose  = get_str('purpose');
$cond     = get_str('condition');
$page     = max(1, get_int('page'));
$perPage  = 12;

$where  = ["books.status = 'available'"];
$types  = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(books.title LIKE ? OR books.author LIKE ?)';
    $types .= 'ss'; array_push($params, $like, $like);
}
if ($cat > 0) { $where[] = 'books.category_id = ?'; $types .= 'i'; $params[] = $cat; }
if (isset(PURPOSES[$purpose])) { $where[] = 'books.purpose = ?'; $types .= 's'; $params[] = $purpose; }
if (isset(CONDITIONS[$cond])) { $where[] = 'books.book_condition = ?'; $types .= 's'; $params[] = $cond; }
$whereSql = implode(' AND ', $where);

$total = (int)db_one("SELECT COUNT(*) c FROM books WHERE $whereSql", $types, $params)['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT books.*, users.full_name, categories.name AS category_name
        FROM books
        JOIN users ON books.owner_id = users.id
        LEFT JOIN categories ON books.category_id = categories.id
        WHERE $whereSql
        ORDER BY books.id DESC
        LIMIT $perPage OFFSET $offset";
$rows = db_all($sql, $types, $params);

$favIds = array_column(db_all("SELECT book_id FROM favorites WHERE user_id = ?", 'i', [$uid]), 'book_id');
$cats = all_categories();

$qs = $_GET; unset($qs['page']);
$base = 'index.php?' . http_build_query($qs);
$ret = 'index.php' . ($_SERVER['QUERY_STRING'] !== '' && preg_match('/^[A-Za-z0-9_=&%.\-]*$/', $_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

page_header('ตลาดหนังสือ', 'home');
?>
<div class="card">
  <h1>ตลาดหนังสือ</h1>
  <form method="get" class="filter-bar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อหนังสือ หรือผู้แต่ง" maxlength="100">
    <select name="category">
      <option value="">ทุกหมวดหมู่</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="purpose">
      <option value="">ขาย + แลกเปลี่ยน</option>
      <?php foreach (PURPOSES as $k => $v): ?><option value="<?= e($k) ?>" <?= $purpose === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <select name="condition">
      <option value="">ทุกสภาพ</option>
      <?php foreach (CONDITIONS as $k => $v): ?><option value="<?= e($k) ?>" <?= $cond === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">ค้นหา</button>
  </form>
  <a class="btn btn-add" href="create.php">+ ลงประกาศหนังสือ</a>

  <?php if (!$rows): ?>
    <p class="empty">ไม่พบหนังสือที่ตรงกับเงื่อนไข</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $r) book_card($r, $uid, $ret, in_array($r['id'], $favIds)); ?>
    </div>
    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?><a class="btn btn-sm btn-ghost" href="<?= e($base . '&page=' . ($page - 1)) ?>">← ก่อนหน้า</a><?php endif; ?>
        <span class="muted">หน้า <?= $page ?> / <?= $pages ?> (<?= $total ?> เล่ม)</span>
        <?php if ($page < $pages): ?><a class="btn btn-sm btn-ghost" href="<?= e($base . '&page=' . ($page + 1)) ?>">ถัดไป →</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php page_footer();
