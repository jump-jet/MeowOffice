<?php

declare(strict_types=1);

function nav_items(): array
{
    return [
        'home.php' => 'Home',
        'tasks.php' => 'Tasks',
        'calendar.php' => 'Calendar',
        'achievements.php' => 'Awards',
        'report.php' => 'Report',
        'users.php' => 'Users',
        'history.php' => 'History',
    ];
}

function render_header(string $title, bool $authed = true): void
{
    $pageTitle = e($title) . ' · MeowOffice';
    $user = $authed ? current_user() : null;
    $unread = $user ? unread_count((int) $user['id']) : 0;
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $ok = flash('ok');
    $err = flash('err');
    $paw = flash('paw');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/meow.css">
</head>
<body class="<?= $authed ? 'app-body' : 'auth-body' ?>">
<div class="paw-bg" aria-hidden="true"></div>
<?php if ($authed && $user): ?>
<header class="topbar">
    <a class="brand" href="home.php">
        <span class="brand-mark">🐾</span>
        <span>
            <strong>MeowOffice</strong>
            <small>soft paws, sharp reports</small>
        </span>
    </a>
    <nav class="nav">
        <?php foreach (nav_items() as $href => $label): ?>
            <a href="<?= e($href) ?>" class="<?= $current === $href ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="who">
        <a class="bell <?= $current === 'notifications.php' ? 'active' : '' ?>" href="notifications.php" title="Notification Center">
            🔔<?php if ($unread > 0): ?><span class="bell-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
        </a>
        <?= user_avatar($user, 'sm') ?>
        <div>
            <strong><?= e($user['name']) ?></strong>
            <small><?= e($user['role']) ?> · <a href="change-password.php">password</a></small>
        </div>
        <a class="btn btn-ghost" href="logout.php">Log out</a>
    </div>
</header>
<?php endif; ?>
<main class="<?= $authed ? 'shell' : 'auth-shell' ?>">
<?php if ($ok): ?><div class="toast toast-ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="toast toast-err"><?= e($err) ?></div><?php endif; ?>
<?php if ($paw): ?>
<div class="paw-pop" role="status">
    <div class="paw-badge">🐾</div>
    <div><strong>Paw badge earned!</strong><br><?= e($paw) ?></div>
</div>
<?php endif; ?>
<?php
}

function render_footer(array $scripts = []): void
{
    ?>
</main>
<footer class="foot">Made with yarn balls in MeowOffice</footer>
<script src="assets/js/meow.js"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Small reusable bits of markup. */
function priority_chip(string $priority): string
{
    $labels = ['low' => 'low', 'normal' => 'normal', 'high' => 'high!'];

    return '<span class="chip chip-' . e($priority) . '">' . e($labels[$priority] ?? $priority) . '</span>';
}

function event_kinds(): array
{
    return [
        'event' => ['🎈', 'Event'],
        'reminder' => ['⏰', 'Admin reminder'],
        'maintenance' => ['🔧', 'Maintenance'],
        'announcement' => ['📣', 'Announcement'],
    ];
}

function nice_date(string $date): string
{
    $ts = strtotime($date);
    $today = date('Y-m-d');
    $day = date('Y-m-d', $ts);
    if ($day === $today) {
        return 'Today';
    }
    if ($day === date('Y-m-d', strtotime('+1 day'))) {
        return 'Tomorrow';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }

    return date('D, M j', $ts);
}
