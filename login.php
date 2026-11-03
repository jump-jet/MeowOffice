<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!db_installed()) {
    header('Location: install.php');
    exit;
}

guest_only();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user);
        log_activity((int) $user['id'], 'login', 'Signed in to MeowOffice');
        flash('ok', 'Welcome back, ' . $user['name'] . '! The yarn is ready.');
        header('Location: report.php');
        exit;
    }

    $userId = $user ? (int) $user['id'] : null;
    log_activity($userId, 'login_failed', 'Failed sign-in for ' . $email);
    $errors[] = 'Those credentials do not match any office cat.';
}

render_header('Login', false);
?>
<section class="card auth-card">
    <div class="auth-art">
        <div>
            <div class="mascot">🐱💼</div>
            <h2>MeowOffice</h2>
            <p>A cozy back office for humans who like cute animals a little too much.</p>
        </div>
    </div>
    <div class="auth-form">
        <h1>Come in</h1>
        <p class="muted">Sign in with your office paws.</p>
        <?php foreach ($errors as $error): ?>
            <div class="toast toast-err"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form class="form" method="post">
            <?= csrf_field() ?>
            <label>Email <input name="email" type="email" value="<?= e($email) ?>" required></label>
            <label>Password <input name="password" type="password" required></label>
            <button type="submit">Login</button>
        </form>
        <p class="links">
            <a href="register.php">Register a new teammate</a>
        </p>
    </div>
</section>
<?php
render_footer();
