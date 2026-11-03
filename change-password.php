<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([(int) $me['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Current password is not quite right.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'New password needs at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if ($new !== '' && password_verify($new, $hash)) {
        $errors[] = 'Pick a fresh password, not the old one.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $me['id']]);
        log_activity((int) $me['id'], 'password_change', 'Updated office password');
        flash('ok', 'Password updated. Your secret yarn is safe.');
        header('Location: change-password.php');
        exit;
    }
}

render_header('Change password');
?>
<section class="grid grid-2">
    <article class="card">
        <h1>Change password</h1>
        <p class="muted">Keep the office door lock fresh.</p>
        <?php foreach ($errors as $error): ?>
            <div class="toast toast-err"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form class="form" method="post">
            <?= csrf_field() ?>
            <label>Current password <input type="password" name="current" required></label>
            <label>New password <input type="password" name="new" minlength="8" required></label>
            <label>Confirm new password <input type="password" name="confirm" minlength="8" required></label>
            <button type="submit">Save new password</button>
        </form>
    </article>
    <article class="card">
        <div class="mascot">🔐🐾</div>
        <h2>Password etiquette</h2>
        <p>Use at least 8 characters. Treat it like a favorite box: private, sturdy, and not under the sofa.</p>
    </article>
</section>
<?php
render_footer();
