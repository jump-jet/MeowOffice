<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!db_installed()) {
    header('Location: install.php');
    exit;
}

if (current_user()) {
    header('Location: home.php');
    exit;
}

header('Location: login.php');
exit;
