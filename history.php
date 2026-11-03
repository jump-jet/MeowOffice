<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();

$action = trim((string) ($_GET['action'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if ($action !== '') {
    $where .= ' AND l.action = ?';
    $params[] = $action;
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM activity_logs l WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$sql = "SELECT l.*, u.name, u.animal
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE $where
        ORDER BY l.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll();

render_header('History');
?>
<section class="card">
    <h1>Activity history</h1>
    <p class="muted">Who padded where, and when.</p>
    <form method="get" class="form" style="grid-template-columns: 1fr auto; display:grid; gap:12px; margin: 16px 0;">
        <select name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $row): ?>
                <option value="<?= e($row['action']) ?>" <?= $action === $row['action'] ? 'selected' : '' ?>>
                    <?= e($row['action']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn-ghost" type="submit">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['created_at']) ?></td>
                    <td>
                        <span class="user-cell">
                            <?= animal_avatar($row['animal'] ?: 'cat', 'sm') ?>
                            <?= e($row['name'] ?: 'Guest') ?>
                        </span>
                    </td>
                    <td><span class="chip"><?= e($row['action']) ?></span></td>
                    <td><?= e($row['details']) ?></td>
                    <td><?= e($row['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="5">The history book is still a blank nap.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pager">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost" href="?action=<?= urlencode($action) ?>&page=<?= $page - 1 ?>">Newer</a>
        <?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a class="btn btn-ghost" href="?action=<?= urlencode($action) ?>&page=<?= $page + 1 ?>">Older</a>
        <?php endif; ?>
    </div>
</section>
<?php
render_footer();
