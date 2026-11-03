<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/animals.php';
require_once __DIR__ . '/layout.php';

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$publicPages = ['login.php', 'register.php', 'install.php'];

if (!in_array($script, $publicPages, true) && !db_installed()) {
    header('Location: install.php');
    exit;
}
