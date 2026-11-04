<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();
$today = date('Y-m-d');
$kinds = event_kinds();

// Logins move achievement stats too, so check on the landing page.
$fresh = sync_achievements($me);
if ($fresh) {
    flash('paw', 'Achievement unlocked: ' . implode(', ', $fresh) . '!');
    redirect('home.php');
}

$stmt = $pdo->prepare("
    SELECT
      SUM(status = 'pending' AND due_date >= ?) AS pending,
      SUM(status = 'pending' AND due_date < ?) AS overdue
    FROM tasks
    WHERE assigned_to = ? OR assigned_to IS NULL
");
$stmt->execute([$today, $today, $myId]);
$taskCounts = array_map('intval', $stmt->fetch() ?: []);
$stats = user_stats($myId);
$unread = unread_count($myId);

// My tasks for today, plus anything overdue.
$stmt = $pdo->prepare("
    SELECT t.*, a.name AS assignee_name
    FROM tasks t LEFT JOIN users a ON a.id = t.assigned_to
    WHERE t.status = 'pending' AND t.due_date <= ? AND (t.assigned_to = ? OR t.assigned_to IS NULL)
    ORDER BY t.due_date, FIELD(t.priority, 'high', 'normal', 'low'), t.id
    LIMIT 6
");
$stmt->execute([$today, $myId]);
$todayTasks = $stmt->fetchAll();

$visibility = $admin ? '' : " AND kind <> 'reminder'";
$stmt = $pdo->prepare("SELECT * FROM events WHERE starts_at >= ? $visibility ORDER BY starts_at LIMIT 5");
$stmt->execute([date('Y-m-d 00:00:00')]);
$upcoming = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM events
    WHERE kind IN ('announcement', 'maintenance') AND starts_at >= ? AND starts_at < ? + INTERVAL 8 DAY
    ORDER BY starts_at LIMIT 2
");
$stmt->execute([date('Y-m-d 00:00:00'), $today]);
$banners = $stmt->fetchAll();

$pendingApprovals = $admin ? (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn() : 0;
$leaderboard = game_leaderboard();

$hour = (int) date('H');
$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
if ($taskCounts['overdue'] > 0) {
    $mood = 'A few tasks are napping past their due date. Shall we wake them up?';
} elseif ($taskCounts['pending'] > 0) {
    $mood = 'Your paw tasks are lined up like treats on a shelf.';
} else {
    $mood = 'Inbox zero, paws up. Maybe a quick round of Paperwork Pounce?';
}

render_header('Home');
?>
<section class="card hero-card">
    <div>
        <h1>Good <?= $greeting ?>, <?= e($me['name']) ?>.</h1>
        <p><?= e($mood) ?></p>
        <p class="muted"><?= e(date('l, F j')) ?></p>
    </div>
    <div class="mascot"><?= user_emoji($me) ?></div>
</section>

<?php foreach ($banners as $banner): ?>
<div class="banner banner-<?= e($banner['kind']) ?>">
    <span class="event-icon"><?= $kinds[$banner['kind']][0] ?></span>
    <div>
        <strong><?= e($banner['title']) ?></strong>
        <span class="muted"> · <?= e(nice_date($banner['starts_at'])) ?> <?= e(date('g:ia', strtotime($banner['starts_at']))) ?></span>
        <?php if ($banner['details']): ?><div class="small"><?= e($banner['details']) ?></div><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($pendingApprovals > 0): ?>
<a class="banner banner-approval" href="notifications.php#approvals">
    <span class="event-icon">🐣</span>
    <div><strong><?= $pendingApprovals ?> new teammate<?= $pendingApprovals === 1 ? ' is' : 's are' ?> waiting for approval.</strong> <span class="muted">Review now →</span></div>
</a>
<?php endif; ?>

<section class="grid grid-4" style="margin-top:20px;">
    <a class="stat card" href="tasks.php?status=pending&scope=mine">
        <div class="label">My pending tasks</div>
        <div class="value"><?= $taskCounts['pending'] ?></div>
    </a>
    <a class="stat card <?= $taskCounts['overdue'] ? 'stat-alert' : '' ?>" href="tasks.php?status=overdue&scope=mine">
        <div class="label">Overdue</div>
        <div class="value"><?= $taskCounts['overdue'] ?></div>
    </a>
    <a class="stat card" href="achievements.php">
        <div class="label">Paw badges</div>
        <div class="value">🐾 <?= $stats['tasks_completed'] ?></div>
    </a>
    <a class="stat card" href="notifications.php">
        <div class="label">Unread alerts</div>
        <div class="value"><?= $unread ?></div>
    </a>
</section>

<section class="grid grid-2" style="margin-top:20px;">
    <article class="card">
        <div class="cal-head">
            <h2>Today's paw tasks</h2>
            <span class="tabs-spacer"></span>
            <a class="btn btn-ghost btn-sm" href="tasks.php">All tasks</a>
        </div>
        <div class="task-list">
        <?php foreach ($todayTasks as $task):
            $overdue = $task['due_date'] < $today; ?>
            <article class="task <?= $overdue ? 'task-overdue' : '' ?>">
                <div class="task-check">
                    <form method="post" action="tasks.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <input type="hidden" name="back" value="home.php">
                        <button class="check" type="submit" title="Mark complete" aria-label="Mark complete">🐾</button>
                    </form>
                </div>
                <div class="task-body">
                    <strong><?= e($task['title']) ?></strong>
                    <div class="task-meta">
                        <?= priority_chip($task['priority']) ?>
                        <?php if ($overdue): ?>
                            <span class="chip chip-overdue">overdue · <?= e(nice_date($task['due_date'])) ?></span>
                        <?php endif; ?>
                        <?php if (!$task['assigned_to']): ?><span class="small">🧺 up for grabs</span><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$todayTasks): ?>
            <p class="empty">✨ Nothing due today. Stretch, yawn, repeat.</p>
        <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="cal-head">
            <h2>Upcoming</h2>
            <span class="tabs-spacer"></span>
            <a class="btn btn-ghost btn-sm" href="calendar.php">Calendar</a>
        </div>
        <?php foreach ($upcoming as $event): ?>
            <div class="event-row event-<?= e($event['kind']) ?>">
                <span class="event-icon"><?= $kinds[$event['kind']][0] ?></span>
                <div>
                    <strong><?= e($event['title']) ?></strong>
                    <div class="muted small"><?= e(nice_date($event['starts_at'])) ?> · <?= e(date('g:ia', strtotime($event['starts_at']))) ?> · <?= e($kinds[$event['kind']][1]) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?>
            <p class="empty">📭 The calendar is a blank nap.</p>
        <?php endif; ?>
    </article>
</section>

<section class="card game-card" style="margin-top:20px;" id="game">
    <div class="game-main">
        <div class="cal-head">
            <h2>🎮 Paperwork Pounce</h2>
            <span class="tabs-spacer"></span>
            <span class="chip chip-soft">Best: <span id="pounce-best"><?= $stats['best_score'] ?></span></span>
        </div>
        <p class="muted small">Catch falling paperwork 📄 (+1) and fish 🐟 (+5). Dodge spilled coffee ☕. Move with ← → / A D, the mouse, or by touch.</p>
        <div class="game-wrap">
            <canvas id="pounce" width="720" height="400"
                    data-endpoint="game-score.php"
                    data-csrf="<?= e(csrf_token()) ?>"
                    data-player="<?= e(user_emoji($me)) ?>"
                    aria-label="Paperwork Pounce game"></canvas>
            <div class="game-overlay" id="pounce-overlay">
                <div class="game-title">🐱📄</div>
                <strong id="pounce-message">Ready to clear the in-tray?</strong>
                <button type="button" id="pounce-start">Play</button>
            </div>
        </div>
    </div>
    <aside class="game-side">
        <h3>🏆 Top pouncers</h3>
        <ol class="leaderboard" id="pounce-board">
            <?php foreach ($leaderboard as $row): ?>
                <li><span><?= $row['emoji'] ?> <?= e($row['name']) ?></span><strong><?= $row['best'] ?></strong></li>
            <?php endforeach; ?>
            <?php if (!$leaderboard): ?>
                <li class="muted">No scores yet. Be the first!</li>
            <?php endif; ?>
        </ol>
        <p class="muted small">Score 50 and 150 to unlock new cat avatars.</p>
    </aside>
</section>
<?php
render_footer(['assets/js/game.js']);
