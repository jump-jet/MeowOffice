<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$pdo = db();
$owned = unlocked_avatars($myId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim((string) ($_POST['name'] ?? ''));
    $choice = (string) ($_POST['avatar'] ?? '');
    [$kind, $key] = array_pad(explode(':', $choice, 2), 2, '');

    if ($name === '' || mb_strlen($name) > 80) {
        flash('err', 'Please share a name (up to 80 characters).');
    } elseif ($kind === 'animal' && isset(animal_choices()[$key])) {
        $pdo->prepare('UPDATE users SET name = ?, animal = ?, cat_avatar = NULL WHERE id = ?')->execute([$name, $key, $myId]);
        log_activity($myId, 'profile_update', 'Now ' . animal_choices()[$key]);
        flash('ok', 'Say hi to ' . animal_choices()[$key] . '!');
    } elseif ($kind === 'cat' && in_array($key, $owned, true)) {
        $pdo->prepare('UPDATE users SET name = ?, cat_avatar = ? WHERE id = ?')->execute([$name, $key, $myId]);
        log_activity($myId, 'profile_update', 'Now wearing ' . cat_avatars()[$key][1]);
        flash('ok', 'Looking sharp, ' . cat_avatars()[$key][1] . '!');
    } else {
        flash('err', 'That avatar is still locked.');
    }
    redirect('profile.php');
}

$current = !empty($me['cat_avatar']) && isset(cat_avatars()[$me['cat_avatar']]) ? 'cat:' . $me['cat_avatar'] : 'animal:' . $me['animal'];
$stats = user_stats($myId);

render_header('My Profile');
?>
<form method="post" class="profile-form">
    <?= csrf_field() ?>
    <section class="card hero-card">
        <div>
            <h1>My profile</h1>
            <p>Pick the party animal that sits beside your name everywhere in MeowOffice.</p>
            <label style="max-width:360px;">Display name <input name="name" maxlength="80" value="<?= e($me['name']) ?>" required></label>
            <p class="muted small" style="margin-top:12px;">🐾 <?= $stats['tasks_completed'] ?> paw badges · 🏆 <?= count(unlocked_achievements($myId)) ?> achievements · 🎮 best score <?= $stats['best_score'] ?></p>
        </div>
        <div class="mascot"><?= user_avatar($me, 'xl') ?></div>
    </section>

    <section class="card" style="margin-top:20px;">
        <h2>Desk animals</h2>
        <p class="muted">Free for everyone. Swap whenever you like.</p>
        <div class="animal-picker">
            <?php foreach (animal_choices_meta() as $key => [$label]): $value = 'animal:' . $key; ?>
                <label class="pick <?= $current === $value ? 'chosen' : '' ?>">
                    <input type="radio" name="avatar" value="<?= e($value) ?>" <?= $current === $value ? 'checked' : '' ?>>
                    <?= animal_avatar($key, 'lg') ?>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card" style="margin-top:20px;">
        <h2>Collectible cats</h2>
        <p class="muted">Earned from <a href="achievements.php"><u>achievements</u></a>. You have <?= count($owned) ?> of <?= count(cat_avatars()) ?>.</p>
        <div class="animal-picker">
            <?php foreach (cat_avatars() as $key => [$emoji, $label]):
                $value = 'cat:' . $key;
                $has = in_array($key, $owned, true);
            ?>
                <label class="pick <?= $has ? '' : 'locked' ?> <?= $current === $value ? 'chosen' : '' ?>" title="<?= e($has ? $label : 'Locked — unlock it on the Awards page') ?>">
                    <input type="radio" name="avatar" value="<?= e($value) ?>" <?= $has ? '' : 'disabled' ?> <?= $current === $value ? 'checked' : '' ?>>
                    <?= cat_avatar_tile($key, 'lg') ?>
                    <span><?= e($has ? $label : '???') ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-save">
        <button type="submit">Save profile</button>
    </div>
</form>
<?php
render_footer();
