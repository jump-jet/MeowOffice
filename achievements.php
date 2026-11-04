<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();

$fresh = sync_achievements($me);
if ($fresh) {
    flash('paw', 'Achievement unlocked: ' . implode(', ', $fresh) . '!');
    redirect('achievements.php');
}

$stats = user_stats($myId);
$unlocked = unlocked_achievements($myId);
$myAvatars = unlocked_avatars($myId);
$defs = array_filter(achievement_defs(), function (array $def) use ($admin): bool {
    return $admin || !$def['admin'];
});

$statKeys = ['tasks_completed', 'logins', 'games', 'best_score', 'arcade_games'];
if ($admin) {
    $statKeys = array_merge($statKeys, ['tasks_created', 'events_created', 'approvals']);
}

// Admin activity board: what each admin has done in the last 30 days.
$adminBoard = [];
if ($admin) {
    $adminBoard = $pdo->query("
        SELECT u.id, u.name, u.animal, u.cat_avatar,
          SUM(l.action = 'task_create') AS tasks,
          SUM(l.action = 'event_create') AS events,
          SUM(l.action IN ('user_approve', 'user_reject')) AS approvals,
          COUNT(l.id) AS total,
          MAX(l.created_at) AS last_seen
        FROM users u
        LEFT JOIN activity_logs l ON l.user_id = u.id AND l.created_at >= NOW() - INTERVAL 30 DAY
        WHERE u.role = 'admin' AND u.status = 'active'
        GROUP BY u.id, u.name, u.animal, u.cat_avatar
        ORDER BY total DESC
    ")->fetchAll();
}

render_header('Achievements');
?>
<section class="card hero-card">
    <div>
        <h1>Paw Achievements</h1>
        <p>You have unlocked <strong><?= count(array_intersect_key($unlocked, $defs)) ?></strong> of <?= count($defs) ?> achievements.</p>
        <div class="bar"><span style="width: <?= (int) round(count(array_intersect_key($unlocked, $defs)) / max(1, count($defs)) * 100) ?>%"></span></div>
    </div>
    <div class="mascot"><?= user_avatar($me, 'xl') ?></div>
</section>

<section class="card" style="margin-top:20px;">
    <h2>Activity milestones</h2>
    <div class="grid grid-4 milestones">
        <?php foreach ($statKeys as $key):
            $value = $stats[$key];
            $goal = next_milestone($value);
        ?>
            <div class="stat">
                <div class="label"><?= e(stat_labels()[$key]) ?></div>
                <div class="value"><?= $value ?></div>
                <div class="bar"><span style="width: <?= (int) round($value / $goal * 100) ?>%"></span></div>
                <div class="muted small">Next milestone: <?= $goal ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card" style="margin-top:20px;">
    <h2>Achievements</h2>
    <div class="ach-grid">
        <?php foreach ($defs as $key => $def):
            $got = isset($unlocked[$key]);
            $avatar = cat_avatars()[$def['avatar']];
            $progress = min(1, $stats[$def['stat']] / $def['goal']);
        ?>
            <article class="ach <?= $got ? 'ach-on' : 'ach-off' ?>">
                <div class="ach-emoji"><?= $got ? $def['emoji'] : '🔒' ?></div>
                <strong><?= e($def['title']) ?><?= $def['admin'] ? ' <span class="chip chip-admin">admin</span>' : '' ?></strong>
                <span class="muted small"><?= e($def['desc']) ?></span>
                <?php if ($got): ?>
                    <span class="small ach-reward"><?= cat_avatar_tile($def['avatar'], 'xs') ?> Unlocked <?= e(nice_date($unlocked[$key])) ?> · <?= e($avatar[1]) ?></span>
                <?php else: ?>
                    <div class="bar"><span style="width: <?= (int) round($progress * 100) ?>%"></span></div>
                    <span class="muted small"><?= min($stats[$def['stat']], $def['goal']) ?> / <?= $def['goal'] ?> · unlocks a new cat</span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card" style="margin-top:20px;">
    <div class="cal-head">
        <h2>Cat collection</h2>
        <span class="tabs-spacer"></span>
        <a class="btn btn-sm" href="profile.php">Wear an avatar</a>
    </div>
    <p class="muted">Every achievement unlocks a collectible cat. You have <?= count($myAvatars) ?> of <?= count(cat_avatars()) ?>.</p>
    <div class="animal-picker">
        <?php foreach (cat_avatars() as $key => [$emoji, $label]):
            $owned = in_array($key, $myAvatars, true); ?>
            <div class="pick <?= $owned ? '' : 'locked' ?> <?= ($me['cat_avatar'] ?? '') === $key ? 'chosen' : '' ?>">
                <?= cat_avatar_tile($key, 'lg') ?>
                <span><?= e($owned ? $label : '???') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($admin): ?>
<section class="card" style="margin-top:20px;">
    <h2>Admin activity · last 30 days</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Admin</th><th>Tasks created</th><th>Calendar entries</th><th>Approvals</th><th>All actions</th><th>Last active</th></tr>
            </thead>
            <tbody>
            <?php foreach ($adminBoard as $row): ?>
                <tr>
                    <td><span class="user-cell"><?= user_avatar($row, 'sm') ?> <?= e($row['name']) ?></span></td>
                    <td><?= (int) $row['tasks'] ?></td>
                    <td><?= (int) $row['events'] ?></td>
                    <td><?= (int) $row['approvals'] ?></td>
                    <td><?= (int) $row['total'] ?></td>
                    <td><?= e($row['last_seen'] ? nice_date($row['last_seen']) : 'No activity') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php
render_footer();
