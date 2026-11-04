<?php

declare(strict_types=1);

/*
 * System Health Monitor checks. Each check is:
 *   ['group', 'name', 'status' => ok|warn|bad|info, 'value', 'hint']
 */

function health_check(string $group, string $name, string $status, string $value, string $hint = ''): array
{
    return compact('group', 'name', 'status', 'value', 'hint');
}

function format_bytes(float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

function format_duration(int $seconds): string
{
    if ($seconds < 3600) {
        return max(1, intdiv($seconds, 60)) . ' min';
    }
    if ($seconds < 86400) {
        return round($seconds / 3600, 1) . ' h';
    }

    return round($seconds / 86400, 1) . ' days';
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '-1') {
        return -1;
    }
    $num = (int) $value;
    switch (strtolower(substr($value, -1))) {
        case 'g':
            return $num * 1024 ** 3;
        case 'm':
            return $num * 1024 ** 2;
        case 'k':
            return $num * 1024;
    }

    return $num;
}

function health_checks(): array
{
    $checks = [];
    $pdo = db();

    // ---- App ----
    $php = PHP_VERSION;
    $checks[] = health_check('App', 'PHP version', version_compare($php, '7.4', '<') ? 'bad' : (version_compare($php, '8.1', '<') ? 'warn' : 'ok'), $php, version_compare($php, '8.1', '<') ? 'PHP 7.4/8.0 are end-of-life; upgrade when you can.' : '');
    $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'json'], function (string $ext): bool {
        return !extension_loaded($ext);
    }));
    $checks[] = health_check('App', 'Required extensions', $missing ? 'bad' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) : 'pdo_mysql, mbstring, json');
    $http = extension_loaded('curl') ? 'curl' : (ini_get('allow_url_fopen') ? 'allow_url_fopen' : '');
    $checks[] = health_check('App', 'Outbound HTTP', $http ? 'ok' : 'warn', $http ?: 'Unavailable', $http ? '' : 'Office Weather needs curl or allow_url_fopen.');
    $limit = ini_bytes((string) ini_get('memory_limit'));
    $peak = memory_get_peak_usage(true);
    $checks[] = health_check('App', 'Memory (this request)', ($limit > 0 && $peak > $limit * 0.7) ? 'warn' : 'ok', format_bytes($peak) . ' of ' . ($limit > 0 ? format_bytes($limit) : 'unlimited'));
    $version = (int) meta_get('schema_version', '0');
    $checks[] = health_check('App', 'Database schema', $version === SCHEMA_VERSION ? 'ok' : 'warn', 'v' . $version . ' (expected v' . SCHEMA_VERSION . ')');

    // ---- Database ----
    $times = [];
    for ($i = 0; $i < 3; $i++) {
        $t = microtime(true);
        $pdo->query('SELECT 1')->fetchColumn();
        $times[] = (microtime(true) - $t) * 1000;
    }
    $ms = round(array_sum($times) / count($times), 2);
    $checks[] = health_check('Database', 'Query latency', $ms < 20 ? 'ok' : ($ms < 100 ? 'warn' : 'bad'), $ms . ' ms', $ms >= 20 ? 'The database is responding slowly.' : '');
    $checks[] = health_check('Database', 'Server version', 'info', (string) $pdo->query('SELECT VERSION()')->fetchColumn());
    try {
        $status = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime', 'Threads_connected')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (isset($status['Uptime'])) {
            $checks[] = health_check('Database', 'Uptime', 'info', format_duration((int) $status['Uptime']));
        }
        if (isset($status['Threads_connected'])) {
            $threads = (int) $status['Threads_connected'];
            $checks[] = health_check('Database', 'Open connections', $threads > 100 ? 'warn' : 'ok', (string) $threads);
        }
    } catch (Throwable $e) {
        $checks[] = health_check('Database', 'Server status', 'info', 'Not permitted for this DB user');
    }
    $size = $pdo->query('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()')->fetchColumn();
    $checks[] = health_check('Database', 'Database size', 'info', format_bytes((float) $size));
    $logs = (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    $checks[] = health_check('Database', 'Activity log rows', $logs > 500000 ? 'warn' : 'ok', number_format($logs), $logs > 500000 ? 'Consider archiving old activity logs.' : '');

    // Clock drift between PHP and MySQL matters: "today" comes from PHP, timestamps from MySQL.
    $dbNow = strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn());
    $drift = abs(time() - $dbNow);
    $checks[] = health_check('Database', 'Clock drift (PHP vs DB)', $drift <= 60 ? 'ok' : 'warn', $drift . ' s',
        $drift > 60 ? 'PHP and MySQL use different time zones (PHP: ' . date_default_timezone_get() . '). Overdue and "today" counts may be off.' : '');

    // ---- Storage ----
    $free = @disk_free_space(__DIR__);
    $total = @disk_total_space(__DIR__);
    if ($free && $total) {
        $pct = $free / $total * 100;
        $checks[] = health_check('Storage', 'Disk space free', $pct < 5 ? 'bad' : ($pct < 15 ? 'warn' : 'ok'), format_bytes($free) . ' (' . round($pct) . '%)');
    }
    $configWritable = is_writable(__DIR__ . '/config.php');
    $checks[] = health_check('Storage', 'Config file', $configWritable ? 'info' : 'ok', $configWritable ? 'Writable' : 'Read-only',
        $configWritable ? 'Fine locally. On a live server, make includes/config.php read-only after install.' : '');

    // ---- Security ----
    $failed = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
    $checks[] = health_check('Security', 'Failed logins (24h)', $failed >= 20 ? 'bad' : ($failed >= 5 ? 'warn' : 'ok'), (string) $failed, $failed >= 5 ? 'See Notification Center → Security watch.' : '');
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $checks[] = health_check('Security', 'Pending approvals', $pending > 0 ? 'warn' : 'ok', (string) $pending, $pending > 0 ? 'New teammates are waiting.' : '');
    $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
    $checks[] = health_check('Security', 'Active admins', $admins === 0 ? 'bad' : 'ok', (string) $admins);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $checks[] = health_check('Security', 'HTTPS', $https ? 'ok' : 'info', $https ? 'On' : 'Off', $https ? '' : 'Fine for local development; use HTTPS in production.');

    // ---- Workload ----
    $overdue = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND due_date < '" . date('Y-m-d') . "'")->fetchColumn();
    $checks[] = health_check('Workload', 'Overdue tasks', $overdue > 0 ? 'warn' : 'ok', (string) $overdue);
    $lastActivity = $pdo->query('SELECT MAX(created_at) FROM activity_logs')->fetchColumn();
    $checks[] = health_check('Workload', 'Last activity', 'info', $lastActivity ? format_duration(max(60, time() - (int) strtotime((string) $lastActivity))) . ' ago' : 'Never');

    // ---- Integrations ----
    $loc = weather_location();
    if (!$loc) {
        $checks[] = health_check('Integrations', 'Weather service', 'info', 'No city set', 'Set a city on the Dashboard weather card.');
    } else {
        $ok = meta_get('weather_last_ok');
        $err = meta_get('weather_last_error');
        $failing = $err && (!$ok || $err > $ok);
        $checks[] = health_check('Integrations', 'Weather service', $failing ? 'warn' : 'ok', $failing ? 'Last fetch failed ' . $err : ($ok ? 'Last fetch ' . $ok : 'Not fetched yet'));
    }

    return $checks;
}

function health_overall(array $checks): string
{
    $rank = ['info' => 0, 'ok' => 0, 'warn' => 1, 'bad' => 2];
    $worst = 0;
    foreach ($checks as $check) {
        $worst = max($worst, $rank[$check['status']] ?? 0);
    }

    return ['ok', 'warn', 'bad'][$worst];
}

function health_status_label(string $status): array
{
    return [
        'ok' => ['✓', 'Healthy'],
        'warn' => ['!', 'Needs attention'],
        'bad' => ['✕', 'Problem'],
        'info' => ['i', 'Info'],
    ][$status] ?? ['?', 'Unknown'];
}
