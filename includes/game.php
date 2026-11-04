<?php

declare(strict_types=1);

/*
 * The Arcade. Every game posts to game-score.php with its key; scores live in
 * game_scores.game. Front-end code: assets/js/arcade.js (shared shell) +
 * assets/js/games/<key>.js.
 */
function game_catalog(): array
{
    return [
        'pounce' => [
            'title' => 'Paperwork Pounce', 'animal' => 'cat', 'tag' => 'Catch & dodge',
            'desc' => 'Catch falling paperwork and fish, dodge the spilled coffee.',
            'how' => 'Move with ← → / A D, the mouse, or touch.',
            'max' => 5000,
        ],
        'hop' => [
            'title' => 'Bunny Hop', 'animal' => 'bunny', 'tag' => 'Runner',
            'desc' => 'Bean the Bunny is late for a meeting! Hop over cactus pots and boxes, grab every carrot.',
            'how' => 'Space / ↑ / click to jump. Jump again in mid-air for a double hop.',
            'max' => 50000,
        ],
        'memory' => [
            'title' => 'Memory Paws', 'animal' => 'owl', 'tag' => 'Matching',
            'desc' => 'Noodle the Owl shuffled the staff photos. Find all 8 pairs.',
            'how' => 'Flip two cards at a time. Fewer moves and less time = more points.',
            'max' => 2000,
        ],
        'peekaboo' => [
            'title' => 'Peekaboo Pals', 'animal' => 'hamster', 'tag' => 'Reflex',
            'desc' => 'Friends pop out of desk drawers. Boop them before they hide, but never boop a rain cloud!',
            'how' => 'Click or tap the animals. 30 seconds. Chain boops for combo points.',
            'max' => 5000,
        ],
        'stack' => [
            'title' => 'Pancake Panda', 'animal' => 'panda', 'tag' => 'Timing',
            'desc' => 'Bao the Panda wants the tallest pancake stack in the office.',
            'how' => 'Space / click / tap to drop the pancake. Perfect drops keep it wide.',
            'max' => 2000,
        ],
        'sheep' => [
            'title' => 'Counting Sheep', 'animal' => 'sheep', 'tag' => 'Attention',
            'desc' => 'Count the sheep jumping the fence. Only the sheep! Pigs and ducks sneak in later.',
            'how' => 'Watch closely, then pick the right number (or press 1–4). Three misses and it\'s nap time.',
            'max' => 20000,
        ],
    ];
}

/** Top players of one game (best score per user). */
function game_leaderboard(string $game = 'pounce', int $limit = 5): array
{
    $stmt = db()->prepare("
        SELECT u.name, u.animal, u.cat_avatar, MAX(g.score) AS best
        FROM game_scores g
        JOIN users u ON u.id = g.user_id
        WHERE u.status = 'active' AND g.game = ?
        GROUP BY u.id, u.name, u.animal, u.cat_avatar
        ORDER BY best DESC
        LIMIT " . max(1, min(20, $limit)));
    $stmt->execute([$game]);

    return array_map(function (array $row): array {
        // avatar is server-built SVG from whitelisted keys; name is plain text.
        return ['name' => $row['name'], 'avatar' => user_avatar($row, 'xs'), 'best' => (int) $row['best']];
    }, $stmt->fetchAll());
}

function game_best(int $userId, string $game): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(score), 0) FROM game_scores WHERE user_id = ? AND game = ?');
    $stmt->execute([$userId, $game]);

    return (int) $stmt->fetchColumn();
}
