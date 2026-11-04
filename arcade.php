<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$games = game_catalog();
$key = (string) ($_GET['game'] ?? '');

if ($key !== '' && !isset($games[$key])) {
    redirect('arcade.php');
}
if (isset($games[$key]['url'])) {
    redirect($games[$key]['url']);
}

// ---- Play view -------------------------------------------------------------
if ($key !== '') {
    $game = $games[$key];
    $best = game_best($myId, $key);
    $board = game_leaderboard($key, 8);

    // SVG for every desk animal + the player, handed to the game scripts.
    $avatars = [];
    foreach (array_keys(animal_choices()) as $animal) {
        $avatars[$animal] = animal_svg($animal);
    }
    $avatars['player'] = user_avatar_svg($me);
    $payload = json_encode([
        'avatars' => $avatars,
        'animals' => array_keys(animal_choices()),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    render_header($game['title']);
    ?>
<section class="arcade-play" id="arcade" data-game="<?= e($key) ?>" data-endpoint="game-score.php" data-csrf="<?= e(csrf_token()) ?>">
    <div class="arcade-main card">
        <div class="cal-head">
            <a class="btn btn-ghost btn-sm" href="arcade.php">← Arcade</a>
            <h1 class="arcade-title"><?= animal_avatar($game['animal'], 'sm') ?> <?= e($game['title']) ?></h1>
            <span class="tabs-spacer"></span>
            <span class="chip chip-soft">Your best: <span id="arcade-best"><?= number_format($best) ?></span></span>
        </div>
        <div class="arcade-hud" aria-live="polite"><span id="hud-left"></span><span id="hud-right"></span></div>
        <div class="arcade-wrap">
            <div class="arcade-stage" id="arcade-stage"></div>
            <div class="game-overlay" id="arcade-overlay">
                <div class="arcade-overlay-art"><?= animal_avatar($game['animal'], 'lg') ?></div>
                <strong id="arcade-message"><?= e($game['desc']) ?></strong>
                <span class="small" id="arcade-how"><?= e($game['how']) ?></span>
                <button type="button" id="arcade-start" disabled>Loading…</button>
            </div>
        </div>
    </div>
    <aside class="card arcade-side">
        <h3>🏆 Top players</h3>
        <ol class="leaderboard" id="arcade-board">
            <?php foreach ($board as $row): ?>
                <li><span class="user-cell small"><?= $row['avatar'] ?> <?= e($row['name']) ?></span><strong><?= number_format($row['best']) ?></strong></li>
            <?php endforeach; ?>
            <?php if (!$board): ?><li class="muted">No scores yet. Be the first!</li><?php endif; ?>
        </ol>
        <h3>How to play</h3>
        <p class="small"><?= e($game['how']) ?></p>
        <p class="muted small">Press <kbd>P</kbd> or <kbd>Esc</kbd> to pause. Play every Arcade game to unlock Disco Kitty 😎.</p>
    </aside>
</section>
<script type="application/json" id="arcade-data"><?= $payload ?></script>
    <?php
    render_footer(['assets/js/arcade.js', 'assets/js/games/' . $key . '.js']);
    exit;
}

// ---- Hub -------------------------------------------------------------------
$stmt = db()->prepare('SELECT game, MAX(score) FROM game_scores WHERE user_id = ? GROUP BY game');
$stmt->execute([$myId]);
$myBests = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$tried = count($myBests);

render_header('Arcade');
?>
<section class="card hero-card">
    <div>
        <h1>🕹️ MeowOffice Arcade</h1>
        <p>Six tiny games for coffee breaks. Every one stars an office animal.</p>
        <p class="muted small">You've tried <?= $tried ?> of <?= count($games) ?> games.<?= $tried < count($games) ? ' Try them all to unlock Disco Kitty!' : ' Disco Kitty is yours 😎' ?></p>
        <div class="bar" style="max-width:360px"><span style="width: <?= (int) round($tried / count($games) * 100) ?>%"></span></div>
    </div>
    <div class="mascot"><?= cat_avatar_tile('disco', 'xl') ?></div>
</section>

<section class="arcade-grid" style="margin-top:20px;">
    <?php foreach ($games as $gkey => $game):
        $top = game_leaderboard($gkey, 1)[0] ?? null;
        $href = $game['url'] ?? 'arcade.php?game=' . $gkey;
    ?>
        <a class="arcade-card card" href="<?= e($href) ?>">
            <div class="arcade-card-art"><?= animal_avatar($game['animal'], 'xl') ?></div>
            <span class="chip chip-soft"><?= e($game['tag']) ?></span>
            <h2><?= e($game['title']) ?></h2>
            <p class="small"><?= e($game['desc']) ?></p>
            <div class="arcade-card-foot small">
                <span>Your best: <strong><?= isset($myBests[$gkey]) ? number_format((int) $myBests[$gkey]) : '—' ?></strong></span>
                <?php if ($top): ?><span class="user-cell small">👑 <?= $top['avatar'] ?> <?= e($top['name']) ?></span><?php endif; ?>
            </div>
            <span class="btn arcade-play-btn">Play</span>
        </a>
    <?php endforeach; ?>
</section>
<?php
render_footer();
