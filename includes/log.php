<?php

declare(strict_types=1);

function log_activity(?int $userId, string $action, ?string $details = null): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Throwable $e) {
        // Never block the page if logging fails.
    }
}
