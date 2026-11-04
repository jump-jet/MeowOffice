<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_admin();
    if (($_POST['action'] ?? '') === 'weather_city') {
        $error = weather_set_city((string) ($_POST['city'] ?? ''));
        if ($error) {
            flash('err', $error);
        } else {
            $loc = weather_location();
            log_activity($myId, 'weather_city', 'Weather now shows ' . $loc['name']);
            flash('ok', 'Weather station moved to ' . $loc['name'] . '.');
        }
    }
    redirect('dashboard.php');
}

$one = function (string $sql, array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

// ---- Stat tiles ----
$todayStart = $today . ' 00:00:00';
$weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$kpis = [
    ['Active teammates', $one("SELECT COUNT(*) FROM users WHERE status = 'active'"), 'users.php', ''],
    ['Logins today', $one("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= ?", [$todayStart]), 'history.php?action=login', ''],
    ['Tasks done · 7 days', $one("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND completed_at >= ?", [$weekAgo]), 'tasks.php?status=completed', ''],
    ['Overdue tasks', $one("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND due_date < ?", [$today]), 'tasks.php?status=overdue&scope=all', 'alert'],
    ['Game rounds today', $one('SELECT COUNT(*) FROM game_scores WHERE created_at >= ?', [$todayStart]), 'arcade.php', ''],
];
$kpis[] = $admin
    ? ['Pending approvals', $one("SELECT COUNT(*) FROM users WHERE status = 'pending'"), 'notifications.php#approvals', 'alert']
    : ['Unread alerts', unread_count($myId), 'notifications.php', ''];

// ---- Daily series for the last 14 days ----
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = 0;
}
$start = array_key_first($days) . ' 00:00:00';
$series = function (string $sql) use ($pdo, $days, $start): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start]);
    $out = $days;
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $day => $count) {
        if (isset($out[$day])) {
            $out[$day] = (int) $count;
        }
    }
    return $out;
};
$logins = $series("SELECT DATE(created_at), COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= ? GROUP BY DATE(created_at)");
$created = $series('SELECT DATE(created_at), COUNT(*) FROM tasks WHERE created_at >= ? GROUP BY DATE(created_at)');
$completed = $series("SELECT DATE(completed_at), COUNT(*) FROM tasks WHERE status = 'completed' AND completed_at >= ? GROUP BY DATE(completed_at)");

$loginPoints = [];
$taskTips = [];
$dayLabels = [];
foreach ($days as $day => $_) {
    $label = date('M j', strtotime($day));
    $dayLabels[] = $label;
    $loginPoints[] = ['label' => $label, 'value' => $logins[$day], 'tip' => date('D, M j', strtotime($day)) . ': ' . $logins[$day] . ' login' . ($logins[$day] === 1 ? '' : 's')];
    $taskTips[] = date('D, M j', strtotime($day)) . ' — created ' . $created[$day] . ', completed ' . $completed[$day];
}

// ---- Busiest hours (last 30 days) ----
$stmt = $pdo->prepare('SELECT HOUR(created_at), COUNT(*) FROM activity_logs WHERE created_at >= ? GROUP BY HOUR(created_at)');
$stmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
$byHour = array_fill(0, 24, 0);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $hour => $count) {
    $byHour[(int) $hour] = (int) $count;
}
$hourPoints = [];
foreach ($byHour as $hour => $count) {
    $label = date('ga', mktime($hour, 0, 0));
    $hourPoints[] = ['label' => $label, 'value' => $count, 'tip' => $label . '–' . date('ga', mktime(($hour + 1) % 24, 0, 0)) . ': ' . $count . ' actions'];
}

// ---- Activity mix (last 7 days) ----
$hidden = $admin ? [] : feed_admin_only_actions();
$stmt = $pdo->prepare('SELECT action, COUNT(*) AS total FROM activity_logs WHERE created_at >= ?'
    . ($hidden ? ' AND action NOT IN (' . implode(',', array_fill(0, count($hidden), '?')) . ')' : '')
    . ' GROUP BY action ORDER BY total DESC LIMIT 8');
$stmt->execute(array_merge([$weekAgo], $hidden));
$mix = array_map(function (array $row): array {
    return ['label' => action_label($row['action']), 'value' => (int) $row['total']];
}, $stmt->fetchAll());

// ---- Widgets ----
$weather = weather_current();
$forecast = office_forecast();
$stmt = $pdo->prepare('
    SELECT a.*, u.name AS author FROM announcements a LEFT JOIN users u ON u.id = a.author_id
    WHERE a.expires_on IS NULL OR a.expires_on >= ?
    ORDER BY a.pinned DESC, a.created_at DESC LIMIT 3
');
$stmt->execute([$today]);
$notes = $stmt->fetchAll();
$feed = feed_items($admin, 0, 12);
$healthChecks = $admin ? health_checks() : [];
$leaderboard = $admin ? [] : game_leaderboard();

render_header('Dashboard');
?>
<section class="dash-head">
    <div>
        <h1>Office Dashboard</h1>
        <p class="muted"><?= e(date('l, F j · g:i a')) ?> · everything happening in MeowOffice at a glance</p>
    </div>
</section>

<section class="grid grid-dash">
    <article class="card weather-card">
        <div class="cal-head">
            <h2>Office Weather</h2>
            <span class="tabs-spacer"></span>
            <?php if (!empty($weather['location'])): ?><span class="chip chip-soft">📍 <?= e($weather['location']['name']) ?></span><?php endif; ?>
        </div>
        <?php if (($weather['status'] ?? '') === 'ok'):
            $cur = $weather['current'];
            [$wIcon, $wLabel, $wQuip] = weather_code_info($cur['code'], $cur['day']); ?>
            <div class="weather-now">
                <span class="weather-icon"><?= $wIcon ?></span>
                <div>
                    <div class="weather-temp"><?= (int) $cur['temp'] ?>°C</div>
                    <div><strong><?= e($wLabel) ?></strong> · feels like <?= (int) $cur['feels'] ?>°</div>
                    <div class="muted small">💧 <?= (int) $cur['humidity'] ?>% humidity · 🍃 <?= (int) $cur['wind'] ?> km/h wind<?= !empty($weather['stale']) ? ' · (offline, showing last reading)' : '' ?></div>
                </div>
            </div>
            <p class="weather-quip"><?= animal_avatar('penguin', 'xs') ?> <?= e($wQuip) ?></p>
            <div class="weather-days">
                <?php foreach ($weather['daily'] as $d):
                    [$dIcon, $dLabel] = weather_code_info($d['code']); ?>
                    <div class="weather-day" title="<?= e($dLabel) ?>">
                        <span class="small"><?= e(nice_date($d['date'])) ?></span>
                        <span class="weather-day-icon"><?= $dIcon ?></span>
                        <strong><?= (int) $d['max'] ?>°</strong> <span class="muted"><?= (int) $d['min'] ?>°</span>
                        <span class="small muted">☔ <?= (int) $d['rain'] ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (($weather['status'] ?? '') === 'offline'): ?>
            <p class="empty">📡 The weather station is napping (couldn't reach the weather service).</p>
        <?php else: ?>
            <p class="empty">🌤️ No city set yet.<?= $admin ? ' Pick one below.' : ' Ask an admin to pick one.' ?></p>
        <?php endif; ?>

        <div class="forecast">
            <span class="forecast-icon"><?= $forecast['emoji'] ?></span>
            <div>
                <span class="muted small">Office forecast</span>
                <strong><?= e($forecast['title']) ?></strong>
                <div class="small"><?= e($forecast['detail']) ?></div>
            </div>
        </div>

        <?php if ($admin): ?>
            <form class="weather-form" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="weather_city">
                <input name="city" maxlength="80" placeholder="City, e.g. Kuala Lumpur" value="<?= e(explode(',', $weather['location']['name'] ?? '')[0]) ?>" required>
                <button class="btn-ghost btn-sm" type="submit">Set city</button>
            </form>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="cal-head">
            <h2>📌 Announcements</h2>
            <span class="tabs-spacer"></span>
            <a class="btn btn-ghost btn-sm" href="board.php">Open board</a>
        </div>
        <div class="mini-notes">
            <?php foreach ($notes as $note): ?>
                <a class="mini-note note-<?= e($note['color']) ?>" href="board.php#note-<?= (int) $note['id'] ?>">
                    <strong><?= $note['pinned'] ? '📌 ' : '' ?><?= e($note['title']) ?></strong>
                    <span class="small"><?= e(mb_strimwidth($note['body'], 0, 120, '…')) ?></span>
                    <span class="muted small"><?= e($note['author'] ?: 'Former teammate') ?> · <?= e(nice_date($note['created_at'])) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$notes): ?>
                <p class="empty">📭 No announcements on the board.</p>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="kpis" style="margin-top:20px;">
    <?php foreach ($kpis as [$label, $value, $href, $flag]): ?>
        <a class="stat card <?= $flag === 'alert' && $value > 0 ? 'stat-alert' : '' ?>" href="<?= e($href) ?>">
            <div class="label"><?= e($label) ?></div>
            <div class="value"><?= number_format($value) ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="grid grid-2 charts" style="margin-top:20px;">
    <article class="card chart-card">
        <h2>Logins per day</h2>
        <p class="muted small">Last 14 days</p>
        <?= column_chart($loginPoints, 'Logins per day, last 14 days') ?>
        <?= chart_table(['Day', 'Logins'], array_map(function ($p) { return [$p['label'], $p['value']]; }, $loginPoints)) ?>
    </article>
    <article class="card chart-card">
        <h2>Tasks created vs completed</h2>
        <p class="muted small">Last 14 days</p>
        <?= line_chart($dayLabels, [
            ['name' => 'Created', 'color' => 'var(--series-1)', 'values' => array_values($created)],
            ['name' => 'Completed', 'color' => 'var(--series-2)', 'values' => array_values($completed)],
        ], $taskTips, 'Tasks created and completed per day, last 14 days') ?>
        <?php
        $taskRows = [];
        foreach ($dayLabels as $i => $label) {
            $taskRows[] = [$label, array_values($created)[$i], array_values($completed)[$i]];
        }
        echo chart_table(['Day', 'Created', 'Completed'], $taskRows);
        ?>
    </article>
    <article class="card chart-card">
        <h2>Busiest hours</h2>
        <p class="muted small">All activity by hour of day, last 30 days</p>
        <?= column_chart($hourPoints, 'Activity by hour of day, last 30 days') ?>
        <?= chart_table(['Hour', 'Actions'], array_map(function ($p) { return [$p['label'], $p['value']]; }, $hourPoints)) ?>
    </article>
    <article class="card chart-card">
        <h2>What the office has been doing</h2>
        <p class="muted small">Top actions, last 7 days</p>
        <?php if ($mix): ?>
            <?= bar_list($mix) ?>
        <?php else: ?>
            <p class="empty">No activity this week.</p>
        <?php endif; ?>
    </article>
</section>

<section class="grid grid-2" style="margin-top:20px;">
    <article class="card">
        <div class="cal-head">
            <h2><span class="live-dot" aria-hidden="true"></span> Live activity</h2>
            <span class="tabs-spacer"></span>
            <span class="muted small" id="feed-status">updates every 5s</span>
        </div>
        <ol class="feed" id="feed" data-feed="feed.php" data-since="<?= $feed ? (int) $feed[0]['id'] : 0 ?>" data-now="<?= time() ?>">
            <?php foreach ($feed as $item): ?>
                <li class="feed-item">
                    <?= $item['avatar'] ?>
                    <div>
                        <strong><?= e($item['who']) ?></strong> <span class="feed-icon"><?= $item['icon'] ?></span> <?= e(mb_strtolower($item['label'])) ?>
                        <?php if ($item['details'] !== ''): ?><div class="muted small"><?= e($item['details']) ?></div><?php endif; ?>
                    </div>
                    <time class="muted small" data-ts="<?= (int) $item['ts'] ?>"><?= e(time_ago($item['ts'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!$feed): ?><p class="empty" id="feed-empty">Waiting for the first paw step…</p><?php endif; ?>
    </article>

    <?php if ($admin):
        $overall = health_overall($healthChecks);
        $issues = array_values(array_filter($healthChecks, function ($c) { return in_array($c['status'], ['warn', 'bad'], true); }));
    ?>
    <article class="card">
        <div class="cal-head">
            <h2>🩺 System health</h2>
            <span class="tabs-spacer"></span>
            <a class="btn btn-ghost btn-sm" href="health.php">Details</a>
        </div>
        <div class="health-banner health-<?= e($overall) ?>">
            <span class="health-dot"><?= health_status_label($overall)[0] ?></span>
            <div>
                <strong><?= e($overall === 'ok' ? 'All systems purring' : count($issues) . ' ' . (count($issues) === 1 ? 'thing needs' : 'things need') . ' attention') ?></strong>
                <div class="small"><?= count($healthChecks) ?> checks run just now</div>
            </div>
        </div>
        <?php foreach (array_slice($issues, 0, 4) as $check):
            [$ci, $cl] = health_status_label($check['status']); ?>
            <div class="health-row">
                <span class="health-pill health-<?= e($check['status']) ?>"><?= $ci ?> <?= e($cl) ?></span>
                <div class="health-text"><strong><?= e($check['name']) ?></strong><?php if ($check['hint']): ?><span class="muted small"><?= e($check['hint']) ?></span><?php endif; ?></div>
                <span class="health-value"><?= e($check['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </article>
    <?php else: ?>
    <article class="card">
        <div class="cal-head">
            <h2>🏆 Top pouncers</h2>
            <span class="tabs-spacer"></span>
            <a class="btn btn-ghost btn-sm" href="arcade.php?game=pounce">Play</a>
        </div>
        <ol class="leaderboard">
            <?php foreach ($leaderboard as $row): ?>
                <li><span class="user-cell small"><?= $row['avatar'] ?> <?= e($row['name']) ?></span><strong><?= (int) $row['best'] ?></strong></li>
            <?php endforeach; ?>
            <?php if (!$leaderboard): ?><li class="muted">No scores yet.</li><?php endif; ?>
        </ol>
    </article>
    <?php endif; ?>
</section>

<div class="chart-tip" id="chart-tip" role="tooltip" hidden></div>
<?php
render_footer(['assets/js/dashboard.js']);
