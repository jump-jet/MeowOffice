<?php

declare(strict_types=1);

function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    try {
        db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $type, mb_substr($title, 0, 120), $body === null ? null : mb_substr($body, 0, 255), $link]);
    } catch (Throwable $e) {
        // Notifications are nice-to-have; never block the page.
    }
}

function notify_admins(string $type, string $title, ?string $body = null, ?string $link = null, ?int $exceptUserId = null): void
{
    $ids = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if ($exceptUserId !== null && (int) $id === $exceptUserId) {
            continue;
        }
        notify((int) $id, $type, $title, $body, $link);
    }
}

function notify_everyone(string $type, string $title, ?string $body = null, ?string $link = null, ?int $exceptUserId = null): void
{
    $ids = db()->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if ($exceptUserId !== null && (int) $id === $exceptUserId) {
            continue;
        }
        notify((int) $id, $type, $title, $body, $link);
    }
}

function unread_count(int $userId): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Notification types, with the icon and label shown in the Notification Center. */
function notification_types(): array
{
    return [
        'registration' => ['🐣', 'New teammates'],
        'security' => ['🚨', 'Failed logins'],
        'admin' => ['🛠️', 'Admin activity'],
        'task' => ['🐾', 'Tasks'],
        'event' => ['📅', 'Calendar'],
        'board' => ['📌', 'Announcements'],
        'achievement' => ['🏆', 'Achievements'],
    ];
}

function notification_icon(string $type): string
{
    return notification_types()[$type][0] ?? '🔔';
}

/** Only allow links to our own pages, e.g. "tasks.php?status=overdue". */
function safe_internal_link(?string $link): string
{
    if ($link !== null && preg_match('/^[a-z0-9\-]+\.php(\?[^\s<>"\']*)?(#[a-z0-9\-]+)?$/i', $link)) {
        return $link;
    }

    return 'notifications.php';
}
