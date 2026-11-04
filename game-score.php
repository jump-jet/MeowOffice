<?php

declare(strict_types=1);

/*
 * JSON endpoint for Paperwork Pounce (assets/js/game.js).
 * POST score + csrf  -> saves the run, returns best score, leaderboard and
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

const MAX_SCORE = 5000;
const MIN_SECONDS_BETWEEN_RUNS = 5;

$myId = (int) $me['id'];
$score = max(0, min(MAX_SCORE, (int) ($_POST['score'] ?? 0)));
$pdo = db();

// Light rate-limit so the leaderboard can't be spammed from the console.
$stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM game_scores WHERE user_id = ?');
$stmt->execute([$myId]);
$since = $stmt->fetchColumn();
if ($since !== null && $since !== false && (int) $since < MIN_SECONDS_BETWEEN_RUNS) {
    http_response_code(429);
    echo json_encode(['error' => 'Slow down, speedy paws.']);
    exit;
}

$stmt = $pdo->prepare('SELECT COALESCE(MAX(score), 0) FROM game_scores WHERE user_id = ?');
$stmt->execute([$myId]);
$previousBest = (int) $stmt->fetchColumn();

$pdo->prepare('INSERT INTO game_scores (user_id, score) VALUES (?, ?)')->execute([$myId, $score]);
log_activity($myId, 'game_play', 'Scored ' . $score . ' in Paperwork Pounce');
$unlocked = sync_achievements($me);

echo json_encode([
    'score' => $score,
    'best' => max($previousBest, $score),
    'newBest' => $score > $previousBest,
    'unlocked' => $unlocked,
    'leaderboard' => game_leaderboard(),
]);
