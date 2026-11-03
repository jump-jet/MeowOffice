<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    log_activity((int) $user['id'], 'logout', 'Left the office for a nap');
}

logout_user();
session_start();
flash('ok', 'See you later, little tiger.');
header('Location: login.php');
exit;
