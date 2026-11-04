<?php

declare(strict_types=1);

// JSON endpoint for the Live Activity Feed (see includes/feed.php).

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in again.']);
    exit;
}

echo json_encode([
    'now' => time(),
    'items' => feed_items(is_admin($me), max(0, (int) ($_GET['since'] ?? 0))),
]);
