<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$pdo = db();

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$loginsToday = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND DATE(created_at) = CURDATE()")->fetchColumn();
$newWeek = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$days = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM activity_logs
    WHERE action = 'login' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day
")->fetchAll();

$byDay = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $byDay[$day] = 0;
}
foreach ($days as $row) {
    $byDay[$row['day']] = (int) $row['total'];
}
$max = max(1, max($byDay));

$actionCounts = $pdo->query("
    SELECT action, COUNT(*) AS total
    FROM activity_logs
    GROUP BY action
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();
$actionMax = 1;
foreach ($actionCounts as $row) {
    $actionMax = max($actionMax, (int) $row['total']);
}

$mood = $loginsToday >= 5 ? 'The office is buzzing like a treat cupboard.' : ($loginsToday > 0 ? 'A few paws are already at their desks.' : 'Quiet morning. Perfect for stretching.');

render_header('Report');
?>
<section class="card hero-card">
    <div>
        <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= e($me['name']) ?>.</h1>
        <p><?= e($mood) ?></p>
        <p class="muted">MeowOffice report for <?= e(date('l, F j')) ?>.</p>
    </div>
    <div class="mascot"><?= animal_emoji($me['animal']) ?></div>
</section>

<section class="grid grid-4" style="margin-top:20px;">
    <article class="stat card">
        <div class="label">Users</div>
        <div class="value"><?= $totalUsers ?></div>
    </article>
    <article class="stat card">
        <div class="label">Admins</div>
        <div class="value"><?= $admins ?></div>
    </article>
    <article class="stat card">
        <div class="label">Logins today</div>
        <div class="value"><?= $loginsToday ?></div>
    </article>
    <article class="stat card">
        <div class="label">New this week</div>
        <div class="value"><?= $newWeek ?></div>
    </article>
</section>

<section class="grid grid-2" style="margin-top:20px;">
    <article class="card">
        <h2>Logins, last 7 days</h2>
        <?php foreach ($byDay as $day => $total): ?>
            <p><?= e(date('D j', strtotime($day))) ?> · <?= $total ?></p>
            <div class="bar"><span style="width: <?= (int) round($total / $max * 100) ?>%"></span></div>
        <?php endforeach; ?>
    </article>
    <article class="card">
        <h2>What the office has been doing</h2>
        <?php foreach ($actionCounts as $row): ?>
            <p><?= e($row['action']) ?> · <?= (int) $row['total'] ?></p>
            <div class="bar"><span style="width: <?= (int) round((int) $row['total'] / $actionMax * 100) ?>%"></span></div>
        <?php endforeach; ?>
        <?php if (!$actionCounts): ?>
            <p class="muted">No activity yet. Take a stroll through the directory.</p>
        <?php endif; ?>
    </article>
</section>
<?php
render_footer();
