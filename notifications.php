<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();

// Open a notification: mark it read, then follow its link.
if (isset($_GET['open'])) {
    $stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['open'], $myId]);
    $link = $stmt->fetchColumn();
    if ($link !== false) {
        $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL')
            ->execute([(int) $_GET['open'], $myId]);
    }
    redirect(safe_internal_link($link === false ? null : $link));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([$myId]);
        flash('ok', 'All caught up. Purrfect.');
        redirect('notifications.php');
    }

    if ($action === 'clear_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND read_at IS NOT NULL')->execute([$myId]);
        flash('ok', 'Read notifications swept under the rug.');
        redirect('notifications.php');
    }

    if ($action === 'approve' || $action === 'reject') {
        require_admin();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $pending = $stmt->fetch();
        if (!$pending) {
            flash('err', 'That request was already handled.');
            redirect('notifications.php');
        }

        $approve = $action === 'approve';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$approve ? 'active' : 'rejected', $userId]);
        log_activity($myId, $approve ? 'user_approve' : 'user_reject', ($approve ? 'Approved ' : 'Rejected ') . $pending['name']);
        notify_admins(
            'admin',
            $me['name'] . ($approve ? ' approved ' : ' rejected ') . $pending['name'],
            $approve ? 'They can now log in.' : 'Their sign-up was declined.',
            'users.php',
            $myId
        );
        if ($approve) {
            notify($userId, 'registration', 'Welcome to MeowOffice! 🎉', 'An admin approved your account. Find your first paw task!', 'tasks.php');
            sync_achievements($me);
        }
        flash('ok', $pending['name'] . ($approve ? ' can now come in.' : ' was turned away at the cat flap.'));
        redirect('notifications.php');
    }

    redirect('notifications.php');
}

$types = notification_types();
$type = (string) ($_GET['type'] ?? '');
if ($type !== '' && !isset($types[$type])) {
    $type = '';
}

$sql = 'SELECT * FROM notifications WHERE user_id = ?';
$params = [$myId];
if ($type !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type;
}
$stmt = $pdo->prepare($sql . ' ORDER BY created_at DESC, id DESC LIMIT 100');
$stmt->execute($params);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT type, COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL GROUP BY type');
$stmt->execute([$myId]);
$unreadByType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$unreadTotal = array_sum($unreadByType);

$pendingUsers = [];
$failedLogins = [];
if ($admin) {
    $pendingUsers = $pdo->query("SELECT id, name, email, animal, cat_avatar, created_at FROM users WHERE status = 'pending' ORDER BY created_at")->fetchAll();
    $failedLogins = $pdo->query("
        SELECT details, COUNT(*) AS attempts, MAX(created_at) AS last_at, COUNT(DISTINCT ip) AS ips
        FROM activity_logs
        WHERE action = 'login_failed' AND created_at >= NOW() - INTERVAL 1 DAY
        GROUP BY details
        ORDER BY attempts DESC
        LIMIT 8
    ")->fetchAll();
}

render_header('Notifications');
?>
<section class="grid <?= $admin ? 'grid-cal' : '' ?>">
    <article class="card">
        <div class="cal-head">
            <h1>Notification Center</h1>
            <span class="tabs-spacer"></span>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <button class="btn-ghost btn-sm" type="submit" <?= $unreadTotal ? '' : 'disabled' ?>>Mark all read</button>
            </form>
            <form method="post" data-confirm="Delete all read notifications?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_read">
                <button class="btn-ghost btn-sm" type="submit">Clear read</button>
            </form>
        </div>
        <div class="tabs">
            <a class="tab <?= $type === '' ? 'active' : '' ?>" href="notifications.php">All <span class="tab-count"><?= $unreadTotal ?></span></a>
            <?php foreach ($types as $key => [$icon, $label]):
                if (!$admin && in_array($key, ['security', 'admin'], true)) {
                    continue;
                } ?>
                <a class="tab <?= $type === $key ? 'active' : '' ?>" href="?type=<?= e($key) ?>">
                    <?= $icon ?> <?= e($label) ?>
                    <?php if (!empty($unreadByType[$key])): ?><span class="tab-count"><?= (int) $unreadByType[$key] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="notice-list">
            <?php foreach ($items as $item): ?>
                <a class="notice <?= $item['read_at'] ? '' : 'notice-unread' ?> notice-<?= e($item['type']) ?>" href="?open=<?= (int) $item['id'] ?>">
                    <span class="notice-icon"><?= notification_icon($item['type']) ?></span>
                    <span class="notice-body">
                        <strong><?= e($item['title']) ?></strong>
                        <?php if ($item['body']): ?><span class="muted"><?= e($item['body']) ?></span><?php endif; ?>
                    </span>
                    <span class="muted small notice-time"><?= e(nice_date($item['created_at'])) ?> <?= e(date('g:ia', strtotime($item['created_at']))) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <p class="empty">🔕 No notifications. The office is purring quietly.</p>
            <?php endif; ?>
        </div>
    </article>

    <?php if ($admin): ?>
    <aside class="stack">
        <article class="card" id="approvals">
            <h2>Pending approvals <span class="chip <?= $pendingUsers ? 'chip-overdue' : '' ?>"><?= count($pendingUsers) ?></span></h2>
            <?php foreach ($pendingUsers as $pending): ?>
                <div class="event-row">
                    <?= user_avatar($pending, 'sm') ?>
                    <div>
                        <strong><?= e($pending['name']) ?></strong>
                        <div class="muted small"><?= e($pending['email']) ?> · <?= e(nice_date($pending['created_at'])) ?></div>
                    </div>
                    <div class="task-actions">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="user_id" value="<?= (int) $pending['id'] ?>">
                            <button class="btn-mint btn-sm" type="submit">Approve</button>
                        </form>
                        <form method="post" data-confirm="Reject this sign-up?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="user_id" value="<?= (int) $pending['id'] ?>">
                            <button class="btn-ghost btn-sm" type="submit">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$pendingUsers): ?>
                <p class="empty">🐾 No one is waiting at the door.</p>
            <?php endif; ?>
        </article>

        <article class="card">
            <h2>Security watch</h2>
            <p class="muted small">Failed logins in the last 24 hours.</p>
            <?php foreach ($failedLogins as $row): ?>
                <div class="event-row">
                    <span class="event-icon"><?= (int) $row['attempts'] >= 5 ? '🚨' : '⚠️' ?></span>
                    <div>
                        <strong><?= e(preg_replace('/^Failed sign-in for /', '', (string) $row['details'])) ?></strong>
                        <div class="muted small"><?= (int) $row['attempts'] ?> attempt<?= (int) $row['attempts'] === 1 ? '' : 's' ?> from <?= (int) $row['ips'] ?> IP<?= (int) $row['ips'] === 1 ? '' : 's' ?> · last <?= e(date('g:ia', strtotime($row['last_at']))) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$failedLogins): ?>
                <p class="empty">🛡️ No failed logins. The cat flap is secure.</p>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="history.php?action=login_failed">Full log</a>
        </article>
    </aside>
    <?php endif; ?>
</section>
<?php
render_footer();
