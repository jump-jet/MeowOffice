<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$admin = is_admin($me);
$q = trim((string) ($_GET['q'] ?? ''));
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detail = null;

if ($id > 0) {
    $stmt = db()->prepare('SELECT id, name, email, role, status, animal, cat_avatar, created_at, last_login_at FROM users WHERE id = ?' . ($admin ? '' : " AND status = 'active'"));
    $stmt->execute([$id]);
    $detail = $stmt->fetch() ?: null;
}

$sql = 'SELECT id, name, email, role, status, animal, cat_avatar, created_at, last_login_at FROM users WHERE ' . ($admin ? '1=1' : "status = 'active'");
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

if ($detail) {
    log_activity((int) $me['id'], 'view_users', 'Viewed ' . $detail['name']);
}

render_header('Users');
?>
<section class="card">
    <h1>Office directory</h1>
    <p class="muted">Every teammate and their desk animal.</p>
    <form class="form" method="get" style="grid-template-columns: 1fr auto; display:grid; gap:12px; margin: 16px 0;">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name or email">
        <button class="btn-ghost" type="submit">Search</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Teammate</th>
                    <th>Email</th>
                    <th>Role</th>
                    <?php if ($admin): ?><th>Status</th><?php endif; ?>
                    <th>Joined</th>
                    <th>Last login</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <a class="user-cell" href="users.php?id=<?= (int) $user['id'] ?>">
                            <?= user_avatar($user, 'sm') ?>
                            <?= e($user['name']) ?>
                        </a>
                    </td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="chip <?= $user['role'] === 'admin' ? 'chip-admin' : '' ?>"><?= e($user['role']) ?></span></td>
                    <?php if ($admin): ?><td><span class="chip chip-status-<?= e($user['status']) ?>"><?= e($user['status']) ?></span></td><?php endif; ?>
                    <td><?= e($user['created_at']) ?></td>
                    <td><?= e($user['last_login_at'] ?: 'Never') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="<?= $admin ? 6 : 5 ?>">No matching kittens in this search.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($detail): ?>
<section class="card" style="margin-top:20px;">
    <div class="user-cell">
        <?= user_avatar($detail, 'lg') ?>
        <div>
            <h2><?= e($detail['name']) ?></h2>
            <p class="muted"><?= e(animal_choices()[$detail['animal']] ?? 'Office friend') ?> · 🐾 <?= user_stats((int) $detail['id'])['tasks_completed'] ?> paw badges · 🏆 <?= count(unlocked_achievements((int) $detail['id'])) ?> achievements</p>
            <p><?= e($detail['email']) ?></p>
            <p><span class="chip <?= $detail['role'] === 'admin' ? 'chip-admin' : '' ?>"><?= e($detail['role']) ?></span></p>
            <p>Joined <?= e($detail['created_at']) ?></p>
            <p>Last login <?= e($detail['last_login_at'] ?: 'Never') ?></p>
        </div>
    </div>
</section>
<?php endif; ?>
<?php
render_footer();
