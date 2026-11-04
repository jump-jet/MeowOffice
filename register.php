<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!db_installed()) {
    header('Location: install.php');
    exit;
}

guest_only();

$errors = [];
$name = '';
$email = '';
$animal = 'bunny';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $animal = (string) ($_POST['animal'] ?? 'bunny');

    if ($name === '' || strlen($name) > 80) {
        $errors[] = 'Please share a name (up to 80 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email looks a little scrambled.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Passwords need at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not purr in unison.';
    }
    if (!array_key_exists($animal, animal_choices())) {
        $animal = 'bunny';
    }

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'That email already has a cubicle here.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "INSERT INTO users (name, email, password_hash, role, status, animal) VALUES (?, ?, ?, 'staff', 'pending', ?)"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $animal]);
        $userId = (int) db()->lastInsertId();
        log_activity($userId, 'register', 'Asked to join MeowOffice as staff');
        notify_admins('registration', 'New sign-up: ' . $name, $email . ' is waiting for approval.', 'notifications.php#approvals');
        flash('ok', 'Thanks, ' . $name . '! An admin will approve your account soon, then you can log in.');
        redirect('login.php');
    }
}

render_header('Register', false);
?>
<section class="card auth-card">
    <div class="auth-art">
        <div>
            <div class="mascot auth-crew"><?php foreach (['frog', 'duck', 'sheep'] as $a): ?><?= animal_avatar($a, 'lg') ?><?php endforeach; ?></div>
            <h2>Join the litter</h2>
            <p>Pick a desk animal. They will sit beside your name forever.</p>
        </div>
    </div>
    <div class="auth-form">
        <h1>Register</h1>
        <?php foreach ($errors as $error): ?>
            <div class="toast toast-err"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form class="form" method="post">
            <?= csrf_field() ?>
            <label>Name <input name="name" value="<?= e($name) ?>" required></label>
            <label>Email <input name="email" type="email" value="<?= e($email) ?>" required></label>
            <label>Password <input name="password" type="password" minlength="8" required></label>
            <label>Confirm password <input name="confirm" type="password" minlength="8" required></label>
            <label>Pick your desk animal</label>
            <?= animal_picker('animal', $animal) ?>
            <button class="btn-mint" type="submit">Create account</button>
        </form>
        <p class="links"><a href="login.php">Already have a badge?</a></p>
    </div>
</section>
<?php
render_footer();
