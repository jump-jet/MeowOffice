<?php

declare(strict_types=1);

/** Top players of Paperwork Pounce (best score per user). */
function game_leaderboard(int $limit = 5): array
{
    $stmt = db()->prepare("
        SELECT u.name, u.animal, u.cat_avatar, MAX(g.score) AS best
        FROM game_scores g
        JOIN users u ON u.id = g.user_id
        WHERE u.status = 'active'
        GROUP BY u.id, u.name, u.animal, u.cat_avatar
        ORDER BY best DESC
        LIMIT $limit
    ");
    $stmt->execute();

    return array_map(function (array $row): array {
        return ['name' => $row['name'], 'emoji' => user_emoji($row), 'best' => (int) $row['best']];
    }, $stmt->fetchAll());
}
