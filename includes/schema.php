<?php

declare(strict_types=1);

/*
 * Single source of truth for the database schema.
 * install.php calls migrate() on a fresh database; bootstrap.php calls
 * ensure_schema() on every request so existing installs upgrade themselves.
 * Bump SCHEMA_VERSION whenever you add a table or column below.
 */
const SCHEMA_VERSION = 2;

function schema_tables(): array
{
    return [
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(80) NOT NULL,
              email VARCHAR(190) NOT NULL UNIQUE,
              password_hash VARCHAR(255) NOT NULL,
              role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
              status ENUM('pending', 'active', 'rejected') NOT NULL DEFAULT 'active',
              animal VARCHAR(32) NOT NULL DEFAULT 'cat',
              cat_avatar VARCHAR(32) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_login_at DATETIME NULL
            ) ENGINE=InnoDB",

        'activity_logs' => "
            CREATE TABLE IF NOT EXISTS activity_logs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NULL,
              action VARCHAR(64) NOT NULL,
              details VARCHAR(255) NULL,
              ip VARCHAR(45) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_user_id (user_id),
              INDEX idx_action (action),
              INDEX idx_created_at (created_at),
              CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        'tasks' => "
            CREATE TABLE IF NOT EXISTS tasks (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              title VARCHAR(120) NOT NULL,
              details VARCHAR(500) NULL,
              assigned_to INT UNSIGNED NULL,
              created_by INT UNSIGNED NULL,
              priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
              due_date DATE NOT NULL,
              status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
              completed_by INT UNSIGNED NULL,
              completed_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_task_assignee (assigned_to, status),
              INDEX idx_task_due (status, due_date),
              INDEX idx_task_completer (completed_by),
              CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_task_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_task_completer FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        'notifications' => "
            CREATE TABLE IF NOT EXISTS notifications (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              type VARCHAR(32) NOT NULL,
              title VARCHAR(120) NOT NULL,
              body VARCHAR(255) NULL,
              link VARCHAR(255) NULL,
              read_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_notify_user (user_id, read_at),
              CONSTRAINT fk_notify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

        'events' => "
            CREATE TABLE IF NOT EXISTS events (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              title VARCHAR(120) NOT NULL,
              details VARCHAR(500) NULL,
              kind ENUM('event', 'reminder', 'maintenance', 'announcement') NOT NULL DEFAULT 'event',
              starts_at DATETIME NOT NULL,
              ends_at DATETIME NULL,
              created_by INT UNSIGNED NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_event_start (starts_at),
              CONSTRAINT fk_event_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        'user_achievements' => "
            CREATE TABLE IF NOT EXISTS user_achievements (
              user_id INT UNSIGNED NOT NULL,
              achievement VARCHAR(48) NOT NULL,
              unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (user_id, achievement),
              CONSTRAINT fk_ach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

        'game_scores' => "
            CREATE TABLE IF NOT EXISTS game_scores (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              score INT UNSIGNED NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_score_user (user_id, score),
              INDEX idx_score (score),
              CONSTRAINT fk_score_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

        'app_meta' => "
            CREATE TABLE IF NOT EXISTS app_meta (
              name VARCHAR(64) PRIMARY KEY,
              value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB",
    ];
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate(PDO $pdo): void
{
    foreach (schema_tables() as $sql) {
        $pdo->exec($sql);
    }

    // v2: columns added to users after the first release.
    if (!column_exists($pdo, 'users', 'status')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('pending', 'active', 'rejected') NOT NULL DEFAULT 'active' AFTER role");
    }
    if (!column_exists($pdo, 'users', 'cat_avatar')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN cat_avatar VARCHAR(32) NULL AFTER animal');
    }

    $pdo->prepare('REPLACE INTO app_meta (name, value) VALUES (?, ?)')
        ->execute(['schema_version', (string) SCHEMA_VERSION]);
}

function ensure_schema(): void
{
    try {
        $version = (int) db()->query("SELECT value FROM app_meta WHERE name = 'schema_version'")->fetchColumn();
    } catch (Throwable $e) {
        $version = 0;
    }

    if ($version < SCHEMA_VERSION) {
        migrate(db());
    }
}
