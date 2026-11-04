<?php

declare(strict_types=1);

/**
 * Achievement definitions. `stat` is a key from user_stats(); the achievement
 * unlocks once that stat reaches `goal`. `admin` ones only apply to admins.
 */
function achievement_defs(): array
{
    return [
        'first_paw' => ['title' => 'First Paw Print', 'desc' => 'Complete your first task.', 'emoji' => '🐾', 'stat' => 'tasks_completed', 'goal' => 1, 'avatar' => 'tabby', 'admin' => false],
        'busy_paws' => ['title' => 'Busy Paws', 'desc' => 'Complete 10 tasks.', 'emoji' => '🧶', 'stat' => 'tasks_completed', 'goal' => 10, 'avatar' => 'sunny', 'admin' => false],
        'task_tiger' => ['title' => 'Task Tiger', 'desc' => 'Complete 50 tasks.', 'emoji' => '🐯', 'stat' => 'tasks_completed', 'goal' => 50, 'avatar' => 'tiger', 'admin' => false],
        'regular' => ['title' => 'Office Regular', 'desc' => 'Log in 10 times.', 'emoji' => '🪑', 'stat' => 'logins', 'goal' => 10, 'avatar' => 'smooch', 'admin' => false],
        'loyal' => ['title' => 'Loyal Lap Cat', 'desc' => 'Log in 50 times.', 'emoji' => '🛋️', 'stat' => 'logins', 'goal' => 50, 'avatar' => 'shadow', 'admin' => false],
        'first_pounce' => ['title' => 'First Pounce', 'desc' => 'Play any Arcade game once.', 'emoji' => '🎮', 'stat' => 'games', 'goal' => 1, 'avatar' => 'giggles', 'admin' => false],
        'pounce_pro' => ['title' => 'Pounce Pro', 'desc' => 'Score 50 in Paperwork Pounce.', 'emoji' => '📄', 'stat' => 'best_score', 'goal' => 50, 'avatar' => 'spooked', 'admin' => false],
        'pounce_legend' => ['title' => 'Pounce Legend', 'desc' => 'Score 150 in Paperwork Pounce.', 'emoji' => '👑', 'stat' => 'best_score', 'goal' => 150, 'avatar' => 'lion', 'admin' => false],
        'arcade_star' => ['title' => 'Arcade Star', 'desc' => 'Play every game in the Arcade.', 'emoji' => '🕹️', 'stat' => 'arcade_games', 'goal' => count(game_catalog()), 'avatar' => 'disco', 'admin' => false],
        'task_setter' => ['title' => 'Task Setter', 'desc' => 'Create 10 tasks for the team.', 'emoji' => '📋', 'stat' => 'tasks_created', 'goal' => 10, 'avatar' => 'sly', 'admin' => true],
        'event_planner' => ['title' => 'Party Planner', 'desc' => 'Add 5 calendar entries.', 'emoji' => '🎉', 'stat' => 'events_created', 'goal' => 5, 'avatar' => 'alley', 'admin' => true],
        'gatekeeper' => ['title' => 'Gatekeeper', 'desc' => 'Approve 5 new teammates.', 'emoji' => '🚪', 'stat' => 'approvals', 'goal' => 5, 'avatar' => 'smitten', 'admin' => true],
    ];
}

/** Labels for the stats tracked on the achievements page. */
function stat_labels(): array
{
    return [
        'tasks_completed' => 'Tasks completed',
        'logins' => 'Logins',
        'games' => 'Games played',
        'best_score' => 'Best Pounce score',
        'arcade_games' => 'Arcade games tried',
        'tasks_created' => 'Tasks created',
        'events_created' => 'Calendar entries',
        'approvals' => 'Teammates approved',
    ];
}

function user_stats(int $userId): array
{
    $pdo = db();
    $one = function (string $sql) use ($pdo, $userId): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    };

    return [
        'tasks_completed' => $one("SELECT COUNT(*) FROM tasks WHERE completed_by = ? AND status = 'completed'"),
        'logins' => $one("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'login'"),
        'games' => $one('SELECT COUNT(*) FROM game_scores WHERE user_id = ?'),
        'best_score' => $one("SELECT COALESCE(MAX(score), 0) FROM game_scores WHERE user_id = ? AND game = 'pounce'"),
        'arcade_games' => $one('SELECT COUNT(DISTINCT game) FROM game_scores WHERE user_id = ?'),
        'tasks_created' => $one('SELECT COUNT(*) FROM tasks WHERE created_by = ?'),
        'events_created' => $one('SELECT COUNT(*) FROM events WHERE created_by = ?'),
        'approvals' => $one("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'user_approve'"),
    ];
}

/** @return array<string, string> achievement key => unlocked_at */
function unlocked_achievements(int $userId): array
{
    $stmt = db()->prepare('SELECT achievement, unlocked_at FROM user_achievements WHERE user_id = ?');
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Unlock any achievements the user has newly earned.
 * Call after actions that move a stat (completing tasks, games, approvals...).
 *
 * @return string[] titles of newly unlocked achievements
 */
function sync_achievements(array $user): array
{
    $userId = (int) $user['id'];
    $isAdmin = is_admin($user);
    $stats = user_stats($userId);
    $have = unlocked_achievements($userId);
    $new = [];

    foreach (achievement_defs() as $key => $def) {
        if (isset($have[$key]) || ($def['admin'] && !$isAdmin) || $stats[$def['stat']] < $def['goal']) {
            continue;
        }
        $stmt = db()->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement) VALUES (?, ?)');
        $stmt->execute([$userId, $key]);
        if ($stmt->rowCount() === 0) {
            continue;
        }
        $avatar = cat_avatars()[$def['avatar']] ?? null;
        $body = $def['desc'] . ($avatar ? ' New avatar unlocked: ' . $avatar[1] . ' ' . $avatar[0] : '');
        notify($userId, 'achievement', $def['emoji'] . ' ' . $def['title'], $body, 'achievements.php');
        log_activity($userId, 'achievement_unlock', 'Unlocked ' . $def['title']);
        $new[] = $def['title'];
    }

    return $new;
}

/** @return string[] cat avatar keys the user may wear */
function unlocked_avatars(int $userId): array
{
    $defs = achievement_defs();
    $keys = [];
    foreach (array_keys(unlocked_achievements($userId)) as $achievement) {
        if (isset($defs[$achievement])) {
            $keys[] = $defs[$achievement]['avatar'];
        }
    }

    return array_values(array_unique($keys));
}

/** Next milestone above a stat value, for progress bars. */
function next_milestone(int $value): int
{
    foreach ([1, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000] as $step) {
        if ($value < $step) {
            return $step;
        }
    }

    return (int) (ceil(($value + 1) / 5000) * 5000);
}
