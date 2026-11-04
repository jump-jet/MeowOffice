<?php

declare(strict_types=1);

function nav_items(): array
{
    return [
        'home.php' => 'Home',
        'dashboard.php' => 'Dashboard',
        'tasks.php' => 'Tasks',
        'calendar.php' => 'Calendar',
        'board.php' => 'Board',
        'arcade.php' => 'Arcade',
        'achievements.php' => 'Awards',
    ];
}

/** Links tucked into the "More" menu. */
function nav_more_items(bool $admin): array
{
    $items = [
        'profile.php' => '🐾 My profile & avatar',
        'users.php' => '👥 Users',
        'history.php' => '📜 History',
    ];
    if ($admin) {
        $items['health.php'] = '🩺 System health';
    }
    $items['change-password.php'] = '🔐 Password';

    return $items;
}

/** The Notification Center icon: a golden cat-bell (inline SVG, no user input). */
function notification_bell_svg(string $class = 'bell-icon'): string
{
    $ink = '#4a2f25';

    return '<svg class="' . $class . '" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<path d="M8.6 13 L7.6 4.2 L13.8 8.6 Z" fill="#ffc94d" stroke="' . $ink . '" stroke-width="1.8" stroke-linejoin="round"/>'
        . '<path d="M23.4 13 L24.4 4.2 L18.2 8.6 Z" fill="#ffc94d" stroke="' . $ink . '" stroke-width="1.8" stroke-linejoin="round"/>'
        . '<path d="M9.6 7.4 L12 9 L9.9 10.6 Z M22.4 7.4 L20 9 L22.1 10.6 Z" fill="#ff9fb6"/>'
        . '<path d="M16 7 C10.6 7 8.4 11 8.4 15.6 V19.8 L6.4 23.4 H25.6 L23.6 19.8 V15.6 C23.6 11 21.4 7 16 7 Z" fill="#ffd34d" stroke="' . $ink . '" stroke-width="1.8" stroke-linejoin="round"/>'
        . '<path d="M10.4 12.4 Q12 9.6 14.6 9" fill="none" stroke="#fff3c4" stroke-width="1.6" stroke-linecap="round"/>'
        . '<circle cx="12.9" cy="15.6" r="1.35" fill="' . $ink . '"/><circle cx="19.1" cy="15.6" r="1.35" fill="' . $ink . '"/>'
        . '<ellipse cx="11" cy="18.4" rx="1.6" ry="0.9" fill="#ff8fab" opacity="0.8"/><ellipse cx="21" cy="18.4" rx="1.6" ry="0.9" fill="#ff8fab" opacity="0.8"/>'
        . '<path d="M14.4 18 Q15.2 19.2 16 18 Q16.8 19.2 17.6 18" fill="none" stroke="' . $ink . '" stroke-width="1.3" stroke-linecap="round"/>'
        . '<circle cx="16" cy="25.6" r="2.3" fill="#ff7a90" stroke="' . $ink . '" stroke-width="1.6"/>'
        . '</svg>';
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
        <?php $more = nav_more_items(is_admin($user)); ?>
        <details class="nav-more <?= isset($more[$current]) ? 'active' : '' ?>">
            <summary>More</summary>
            <div class="nav-menu">
                <?php foreach ($more as $href => $label): ?>
                    <a href="<?= e($href) ?>" class="<?= $current === $href ? 'active' : '' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
    </nav>
    <div class="who">
        <a class="bell <?= $current === 'notifications.php' ? 'active' : '' ?> <?= $unread > 0 ? 'bell-ringing' : '' ?>" href="notifications.php" title="Notification Center<?= $unread > 0 ? ' · ' . $unread . ' unread' : '' ?>" aria-label="Notification Center<?= $unread > 0 ? ', ' . $unread . ' unread' : '' ?>">
            <?= notification_bell_svg() ?><?php if ($unread > 0): ?><span class="bell-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
        </a>
        <a class="who-link" href="profile.php" title="Change your avatar">
            <?= user_avatar($user, 'sm') ?>
            <span>
                <strong><?= e($user['name']) ?></strong>
                <small><?= e($user['role']) ?></small>
            </span>
        </a>
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
