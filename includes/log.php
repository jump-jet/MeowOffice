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

/** Friendly names for activity_logs.action values, shown in History and Report. */
function action_labels(): array
{
    return [
        'install' => ['📦', 'Office opened'],
        'login' => ['🔑', 'Logged in'],
        'login_failed' => ['🚫', 'Failed login'],
        'logout' => ['👋', 'Logged out'],
        'register' => ['🐣', 'Signed up'],
        'password_change' => ['🔐', 'Changed password'],
        'view_users' => ['👀', 'Viewed a teammate'],
        'user_approve' => ['✅', 'Approved a teammate'],
        'user_reject' => ['⛔', 'Rejected a sign-up'],
        'task_create' => ['📋', 'Created a task'],
        'task_complete' => ['🐾', 'Completed a task'],
        'task_reopen' => ['↩️', 'Reopened a task'],
        'task_delete' => ['🗑️', 'Deleted a task'],
        'event_create' => ['📅', 'Added a calendar entry'],
        'event_delete' => ['🗓️', 'Removed a calendar entry'],
        'achievement_unlock' => ['🏆', 'Unlocked an achievement'],
        'avatar_change' => ['😺', 'Changed avatar'],
        'game_play' => ['🎮', 'Played Paperwork Pounce'],
    ];
}

function action_label(string $action, bool $withEmoji = true): string
{
    $labels = action_labels();
    if (isset($labels[$action])) {
        return ($withEmoji ? $labels[$action][0] . ' ' : '') . $labels[$action][1];
    }

    return ucfirst(str_replace('_', ' ', $action));
}
