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
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/animals.php';
require_once __DIR__ . '/achievements.php';
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/layout.php';

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$publicPages = ['login.php', 'register.php', 'install.php'];

$installed = db_installed();
if ($installed) {
    // Upgrades older installs (new tables/columns) the first time a page loads.
    ensure_schema();
} elseif (!in_array($script, $publicPages, true)) {
    header('Location: install.php');
    exit;
}
