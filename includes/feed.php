<?php

declare(strict_types=1);

/*
 * Live Activity Feed. dashboard.php renders the first batch; assets/js/dashboard.js
 * polls feed.php?since=<last id> every few seconds for newer rows.
 */

/** Actions only admins see in the feed (they can expose emails or browsing). */
function feed_admin_only_actions(): array
{
    return ['login_failed', 'view_users', 'user_reject'];
}

function feed_items(bool $admin, int $sinceId = 0, int $limit = 20): array
{
    $sql = 'SELECT l.id, l.action, l.details, l.created_at, u.name, u.animal, u.cat_avatar
            FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
            WHERE l.id > ?';
    $params = [$sinceId];
    if (!$admin) {
        $hidden = feed_admin_only_actions();
        $sql .= ' AND l.action NOT IN (' . implode(',', array_fill(0, count($hidden), '?')) . ')';
        $params = array_merge($params, $hidden);
    }
    $stmt = db()->prepare($sql . ' ORDER BY l.id DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute($params);

    return array_map(function (array $row): array {
        $labels = action_labels();
        return [
            'id' => (int) $row['id'],
            'who' => $row['name'] ?: 'Someone',
            // Server-built SVG from whitelisted keys only; safe to insert as HTML.
            'avatar' => user_avatar(['animal' => $row['animal'] ?: 'cat', 'cat_avatar' => $row['cat_avatar']], 'sm'),
            'icon' => $labels[$row['action']][0] ?? '🐾',
            'label' => action_label($row['action'], false),
            'details' => (string) $row['details'],
            'ts' => strtotime((string) $row['created_at']),
        ];
    }, $stmt->fetchAll());
}

function time_ago(int $ts, ?int $now = null): string
{
    $diff = max(0, ($now ?? time()) - $ts);
    if ($diff < 45) {
        return 'just now';
    }
    if ($diff < 3600) {
        return round($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return round($diff / 3600) . 'h ago';
    }

    return round($diff / 86400) . 'd ago';
}
