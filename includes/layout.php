<?php

declare(strict_types=1);

function render_header(string $title, bool $authed = true): void
{
    $pageTitle = e($title) . ' · MeowOffice';
    $user = $authed ? current_user() : null;
    $ok = flash('ok');
    $err = flash('err');
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
    <a class="brand" href="report.php">
        <span class="brand-mark">🐾</span>
        <span>
            <strong>MeowOffice</strong>
            <small>soft paws, sharp reports</small>
        </span>
    </a>
    <nav class="nav">
        <a href="report.php">Report</a>
        <a href="users.php">Users</a>
        <a href="history.php">History</a>
        <a href="change-password.php">Password</a>
    </nav>
    <div class="who">
        <?= animal_avatar($user['animal'], 'sm') ?>
        <div>
            <strong><?= e($user['name']) ?></strong>
            <small><?= e($user['role']) ?></small>
        </div>
        <a class="btn btn-ghost" href="logout.php">Log out</a>
    </div>
</header>
<?php endif; ?>
<main class="<?= $authed ? 'shell' : 'auth-shell' ?>">
<?php if ($ok): ?><div class="toast toast-ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="toast toast-err"><?= e($err) ?></div><?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="foot">Made with yarn balls in MeowOffice</footer>
<script src="assets/js/meow.js"></script>
</body>
</html>
<?php
}
