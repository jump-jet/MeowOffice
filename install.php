<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (db_installed()) {
    flash('ok', 'MeowOffice is already set up. Welcome back!');
    header('Location: login.php');
    exit;
}

$errors = [];
$name = 'Office Admin';
$email = 'admin@meow.office';
$animal = 'cat';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    global $config;
    $config['db']['host'] = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $config['db']['port'] = (int) ($_POST['db_port'] ?? 3306);
    $config['db']['name'] = trim((string) ($_POST['db_name'] ?? 'meowoffice'));
    $config['db']['user'] = trim((string) ($_POST['db_user'] ?? 'root'));
    $config['db']['pass'] = (string) ($_POST['db_pass'] ?? '');

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $animal = (string) ($_POST['animal'] ?? 'cat');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $errors[] = 'Admin name, a valid email, and an 8+ character password are required.';
    }
    if (!array_key_exists($animal, animal_choices())) {
        $animal = 'cat';
    }

    if (!$errors) {
        try {
            $server = db_server();
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $config['db']['name']) ?: 'meowoffice';
            $config['db']['name'] = $dbName;
            $server->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo = db();
            migrate($pdo);

            $configExport = '<?php' . "\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'app_name' => 'MeowOffice',
                'db' => [
                    'host' => $config['db']['host'],
                    'port' => $config['db']['port'],
                    'name' => $config['db']['name'],
                    'user' => $config['db']['user'],
                    'pass' => $config['db']['pass'],
                    'charset' => 'utf8mb4',
                ],
            ], true) . ";\n";
            file_put_contents(__DIR__ . '/includes/config.php', $configExport);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, status, animal) VALUES (?, ?, ?, ?, 'active', ?)"
            );
            $stmt->execute([$name, $email, $hash, 'admin', $animal]);
            $userId = (int) $pdo->lastInsertId();
            log_activity($userId, 'install', 'Office opened for the first time');

            flash('ok', 'The office is ready. Come in with your new admin paws.');
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Could not talk to MySQL. Check host, user, and password. ' . $e->getMessage();
        }
    }
}

global $config;
render_header('Install', false);
?>
<section class="card auth-card">
    <div class="auth-art">
        <div>
            <div class="mascot">🐱📦</div>
            <h2>Unpack MeowOffice</h2>
            <p>Create the MySQL database, then the first admin kitten.</p>
        </div>
    </div>
    <div class="auth-form">
        <h1>First-time setup</h1>
        <?php foreach ($errors as $error): ?>
            <div class="toast toast-err"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form class="form" method="post">
            <?= csrf_field() ?>
            <h3>MySQL</h3>
            <label>Host <input name="db_host" value="<?= e($config['db']['host']) ?>" required></label>
            <label>Port <input name="db_port" value="<?= e((string) $config['db']['port']) ?>" required></label>
            <label>Database <input name="db_name" value="<?= e($config['db']['name']) ?>" required></label>
            <label>User <input name="db_user" value="<?= e($config['db']['user']) ?>" required></label>
            <label>Password <input name="db_pass" type="password" value="<?= e($config['db']['pass']) ?>"></label>
            <h3>First admin</h3>
            <label>Name <input name="name" value="<?= e($name) ?>" required></label>
            <label>Email <input name="email" type="email" value="<?= e($email) ?>" required></label>
            <label>Password <input name="password" type="password" minlength="8" required></label>
            <div class="animals">
                <?php foreach (animal_choices() as $key => $label): ?>
                    <label>
                        <input type="radio" name="animal" value="<?= e($key) ?>" <?= $animal === $key ? 'checked' : '' ?>>
                        <?= animal_emoji($key) ?> <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit">Open the office</button>
        </form>
        <p class="muted">After install, edit <code>includes/config.php</code> if your MySQL settings differ from the defaults.</p>
    </div>
</section>
<?php
render_footer();
