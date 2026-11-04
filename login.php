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
        if ($user['status'] === 'pending') {
            $errors[] = 'Your account is waiting for an admin to approve it. Hang tight!';
        } elseif ($user['status'] === 'rejected') {
            $errors[] = 'This account was not approved. Please talk to an office admin.';
        } else {
            login_user($user);
            log_activity((int) $user['id'], 'login', 'Signed in to MeowOffice');
            flash('ok', 'Welcome back, ' . $user['name'] . '! The yarn is ready.');
            redirect('home.php');
        }
    } else {
        $userId = $user ? (int) $user['id'] : null;
        $details = 'Failed sign-in for ' . $email;
        log_activity($userId, 'login_failed', $details);

        // Alert admins on the 1st, 3rd and 10th failure for an email within 15 minutes.
        $stmt = db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND details = ? AND created_at >= NOW() - INTERVAL 15 MINUTE");
        $stmt->execute([$details]);
        $attempts = (int) $stmt->fetchColumn();
        if (in_array($attempts, [1, 3, 10], true)) {
            $title = $attempts >= 10 ? 'Possible break-in attempt' : ($attempts >= 3 ? 'Repeated failed logins' : 'Failed login attempt');
            notify_admins('security', $title, $attempts . ' failed sign-in' . ($attempts === 1 ? '' : 's') . ' for ' . $email . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown IP'), 'history.php?action=login_failed');
        }

        $errors[] = 'Those credentials do not match any office cat.';
    }
}

render_header('Login', false);
?>
<section class="card auth-card">
    <div class="auth-art">
        <div>
            <div class="mascot auth-crew"><?php foreach (['bunny', 'cat', 'panda'] as $a): ?><?= animal_avatar($a, 'lg') ?><?php endforeach; ?></div>
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
