<?php

declare(strict_types=1);

/*
 * JSON endpoint for every Arcade game (assets/js/arcade.js).
 * POST game + score + csrf  -> saves the run, returns best score, leaderboard and
 * any achievements unlocked by it.
 */

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$me = current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in again.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only.']);
    exit;
}
csrf_verify();

const MIN_SECONDS_BETWEEN_RUNS = 3;

$games = game_catalog();
$game = (string) ($_POST['game'] ?? 'pounce');
if (!isset($games[$game])) {
    http_response_code(422);
    echo json_encode(['error' => 'Unknown game.']);
    exit;
}

$myId = (int) $me['id'];
$score = max(0, min($games[$game]['max'], (int) ($_POST['score'] ?? 0)));
$pdo = db();

// Light rate-limit so leaderboards can't be spammed from the console.
$stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM game_scores WHERE user_id = ? AND game = ?');
$stmt->execute([$myId, $game]);
$since = $stmt->fetchColumn();
if ($since !== null && $since !== false && (int) $since < MIN_SECONDS_BETWEEN_RUNS) {
    http_response_code(429);
    echo json_encode(['error' => 'Slow down, speedy paws.']);
    exit;
}

$previousBest = game_best($myId, $game);
$pdo->prepare('INSERT INTO game_scores (user_id, game, score) VALUES (?, ?, ?)')->execute([$myId, $game, $score]);
log_activity($myId, 'game_play', 'Scored ' . $score . ' in ' . $games[$game]['title']);
$unlocked = sync_achievements($me);

echo json_encode([
    'score' => $score,
    'best' => max($previousBest, $score),
    'newBest' => $score > $previousBest,
    'unlocked' => $unlocked,
    'leaderboard' => game_leaderboard($game),
]);
