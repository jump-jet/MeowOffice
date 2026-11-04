<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();
$today = date('Y-m-d');

/** Where to send the user after a POST (only whitelisted pages). */
function task_back(): string
{
    $back = (string) ($_POST['back'] ?? '');

    return in_array($back, ['home.php', 'tasks.php'], true) ? $back : 'tasks.php';
}

function find_task(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        require_admin();
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $assignee = (int) ($_POST['assigned_to'] ?? 0);
        $priority = (string) ($_POST['priority'] ?? 'normal');
        $due = (string) ($_POST['due_date'] ?? $today);
        $days = max(1, min(30, (int) ($_POST['repeat_days'] ?? 1)));

        $dueTs = DateTime::createFromFormat('Y-m-d', $due);
        if ($title === '' || mb_strlen($title) > 120) {
            flash('err', 'Give the task a title (up to 120 characters).');
        } elseif (!$dueTs || $dueTs->format('Y-m-d') !== $due) {
            flash('err', 'That due date looks like a hairball.');
        } else {
            if (!in_array($priority, ['low', 'normal', 'high'], true)) {
                $priority = 'normal';
            }
            $assigneeName = null;
            if ($assignee > 0) {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$assignee]);
                $assigneeName = $stmt->fetchColumn() ?: null;
            }
            $assigneeId = $assigneeName ? $assignee : null;

            $insert = $pdo->prepare(
                'INSERT INTO tasks (title, details, assigned_to, created_by, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)'
            );
            for ($i = 0; $i < $days; $i++) {
                $day = (clone $dueTs)->modify("+$i day")->format('Y-m-d');
                $insert->execute([$title, $details === '' ? null : mb_substr($details, 0, 500), $assigneeId, $myId, $priority, $day]);
            }

            $what = $days > 1 ? "\"$title\" daily for $days days" : "\"$title\"";
            $who = $assigneeName ?: 'anyone';
            log_activity($myId, 'task_create', 'Created ' . $what . ' for ' . $who);
            if ($assigneeId && $assigneeId !== $myId) {
                notify($assigneeId, 'task', 'New paw task: ' . $title, 'Due ' . nice_date($due) . ($days > 1 ? ", repeats for $days days" : ''), 'tasks.php');
            }
            notify_admins('admin', $me['name'] . ' created a task', ucfirst($what) . ' for ' . $who, 'tasks.php', $myId);
            sync_achievements($me);
            flash('ok', $days > 1 ? "Created $days daily tasks." : 'Task created.');
        }
        redirect('tasks.php');
    }

    $task = find_task((int) ($_POST['id'] ?? 0));
    if (!$task) {
        flash('err', 'That task wandered off.');
        redirect(task_back());
    }

    if ($action === 'complete') {
        $mine = $task['assigned_to'] === null || (int) $task['assigned_to'] === $myId;
        if (!$mine && !$admin) {
            flash('err', 'That task belongs to another cat.');
        } elseif ($task['status'] === 'completed') {
            flash('ok', 'Already done. Nice!');
        } else {
            $pdo->prepare("UPDATE tasks SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ? AND status = 'pending'")
                ->execute([$myId, (int) $task['id']]);
            log_activity($myId, 'task_complete', 'Completed "' . $task['title'] . '"');
            if ($task['created_by'] !== null && (int) $task['created_by'] !== $myId) {
                notify((int) $task['created_by'], 'task', $me['name'] . ' finished a task', '"' . $task['title'] . '" is done.', 'tasks.php?status=completed');
            }
            $badges = user_stats($myId)['tasks_completed'];
            $unlocked = sync_achievements($me);
            $message = '"' . $task['title'] . '" is done. You now have ' . $badges . ' paw ' . ($badges === 1 ? 'badge' : 'badges') . '.';
            if ($unlocked) {
                $message .= ' Achievement unlocked: ' . implode(', ', $unlocked) . '!';
            }
            flash('paw', $message);
        }
        redirect(task_back());
    }

    if ($action === 'reopen') {
        require_admin();
        $pdo->prepare("UPDATE tasks SET status = 'pending', completed_by = NULL, completed_at = NULL WHERE id = ?")
            ->execute([(int) $task['id']]);
        log_activity($myId, 'task_reopen', 'Reopened "' . $task['title'] . '"');
        flash('ok', 'Task reopened.');
        redirect('tasks.php');
    }

    if ($action === 'delete') {
        require_admin();
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([(int) $task['id']]);
        log_activity($myId, 'task_delete', 'Deleted "' . $task['title'] . '"');
        flash('ok', 'Task deleted.');
        redirect('tasks.php');
    }

    redirect('tasks.php');
}

// ---- Listing ---------------------------------------------------------------

$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'overdue', 'completed', 'all'], true)) {
    $status = 'pending';
}
$scope = $admin ? (string) ($_GET['scope'] ?? 'all') : 'mine';
if (!in_array($scope, ['mine', 'all'], true)) {
    $scope = 'all';
}

$where = [];
$params = [];
if ($scope === 'mine') {
    $where[] = '(t.assigned_to = ? OR t.assigned_to IS NULL)';
    $params[] = $myId;
}
if ($status === 'pending') {
    $where[] = "t.status = 'pending' AND t.due_date >= ?";
    $params[] = $today;
} elseif ($status === 'overdue') {
    $where[] = "t.status = 'pending' AND t.due_date < ?";
    $params[] = $today;
} elseif ($status === 'completed') {
    $where[] = "t.status = 'completed'";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = $status === 'completed' ? 't.completed_at DESC' : "t.status = 'completed', t.due_date, FIELD(t.priority, 'high', 'normal', 'low'), t.id";

$stmt = $pdo->prepare("
    SELECT t.*, a.name AS assignee_name, a.animal AS assignee_animal, a.cat_avatar AS assignee_cat,
           c.name AS completer_name
    FROM tasks t
    LEFT JOIN users a ON a.id = t.assigned_to
    LEFT JOIN users c ON c.id = t.completed_by
    $whereSql
    ORDER BY $order
    LIMIT 200
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Counters for the tabs, using the same scope.
$scopeSql = $scope === 'mine' ? 'WHERE (assigned_to = ? OR assigned_to IS NULL)' : 'WHERE 1=1';
$scopeParams = $scope === 'mine' ? [$myId] : [];
$counts = $pdo->prepare("
    SELECT
      SUM(status = 'pending' AND due_date >= ?) AS pending,
      SUM(status = 'pending' AND due_date < ?) AS overdue,
      SUM(status = 'completed') AS completed,
      COUNT(*) AS total
    FROM tasks $scopeSql
");
$counts->execute(array_merge([$today, $today], $scopeParams));
$count = array_map('intval', $counts->fetch() ?: []);

$users = $admin ? $pdo->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name")->fetchAll() : [];
$myBadges = user_stats($myId)['tasks_completed'];

render_header('Paw Tasks');
?>
<section class="card hero-card">
    <div>
        <h1>Paw Tasks</h1>
        <p><?= $admin ? 'Hand out daily chores and see who is pouncing on them.' : 'Your to-do pile. Every finished task earns a paw badge.' ?></p>
        <p class="muted">You have earned <strong><?= $myBadges ?></strong> paw <?= $myBadges === 1 ? 'badge' : 'badges' ?>.</p>
    </div>
    <div class="paw-tally" aria-label="<?= $myBadges ?> paw badges">
        <?php for ($i = 0; $i < min($myBadges, 24); $i++): ?><span>🐾</span><?php endfor; ?>
        <?php if ($myBadges === 0): ?><span class="muted">No badges yet</span><?php endif; ?>
        <?php if ($myBadges > 24): ?><span class="chip">+<?= $myBadges - 24 ?></span><?php endif; ?>
    </div>
</section>

<?php if ($admin): ?>
<section class="card" style="margin-top:20px;">
    <h2>Create a task</h2>
    <form class="form form-inline" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label class="span-2">Title <input name="title" maxlength="120" placeholder="Water the office cactus" required></label>
        <label>Assign to
            <select name="assigned_to">
                <option value="0">Anyone (up for grabs)</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Priority
            <select name="priority">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="low">Low</option>
            </select>
        </label>
        <label>Due date <input type="date" name="due_date" value="<?= e($today) ?>" required></label>
        <label>Repeat daily for
            <select name="repeat_days">
                <?php foreach ([1 => 'Just once', 5 => '5 days', 7 => '7 days', 14 => '14 days', 30 => '30 days'] as $n => $label): ?>
                    <option value="<?= $n ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">Details <input name="details" maxlength="500" placeholder="Optional notes"></label>
        <button type="submit">Add task</button>
    </form>
</section>
<?php endif; ?>

<section class="card" style="margin-top:20px;">
    <div class="tabs">
        <?php foreach (['pending' => 'Pending', 'overdue' => 'Overdue', 'completed' => 'Completed', 'all' => 'All'] as $key => $label): ?>
            <a class="tab <?= $status === $key ? 'active' : '' ?> <?= $key === 'overdue' && $count['overdue'] > 0 ? 'tab-alert' : '' ?>"
               href="?status=<?= $key ?>&scope=<?= e($scope) ?>">
                <?= e($label) ?> <span class="tab-count"><?= $key === 'all' ? $count['total'] : $count[$key] ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($admin): ?>
            <span class="tabs-spacer"></span>
            <a class="tab <?= $scope === 'all' ? 'active' : '' ?>" href="?status=<?= e($status) ?>&scope=all">Everyone</a>
            <a class="tab <?= $scope === 'mine' ? 'active' : '' ?>" href="?status=<?= e($status) ?>&scope=mine">Mine</a>
        <?php endif; ?>
    </div>

    <div class="task-list">
    <?php foreach ($tasks as $task):
        $overdue = $task['status'] === 'pending' && $task['due_date'] < $today;
        $canComplete = $task['status'] === 'pending' && ($admin || $task['assigned_to'] === null || (int) $task['assigned_to'] === $myId);
    ?>
        <article class="task <?= $task['status'] === 'completed' ? 'task-done' : '' ?> <?= $overdue ? 'task-overdue' : '' ?>">
            <div class="task-check">
                <?php if ($canComplete): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <button class="check" type="submit" title="Mark complete" aria-label="Mark complete">🐾</button>
                    </form>
                <?php else: ?>
                    <span class="check check-static"><?= $task['status'] === 'completed' ? '✅' : '⏳' ?></span>
                <?php endif; ?>
            </div>
            <div class="task-body">
                <strong><?= e($task['title']) ?></strong>
                <?php if ($task['details']): ?><p class="muted"><?= e($task['details']) ?></p><?php endif; ?>
                <div class="task-meta">
                    <?= priority_chip($task['priority']) ?>
                    <?php if ($overdue): ?>
                        <span class="chip chip-overdue">overdue · <?= e(nice_date($task['due_date'])) ?></span>
                    <?php elseif ($task['status'] === 'pending'): ?>
                        <span class="chip chip-soft">due <?= e(nice_date($task['due_date'])) ?></span>
                    <?php else: ?>
                        <span class="chip chip-soft">done by <?= e($task['completer_name'] ?: 'someone') ?> · <?= e(nice_date((string) $task['completed_at'])) ?></span>
                    <?php endif; ?>
                    <span class="user-cell small">
                        <?php if ($task['assigned_to']): ?>
                            <?= user_avatar(['animal' => $task['assignee_animal'], 'cat_avatar' => $task['assignee_cat']], 'xs') ?>
                            <?= e($task['assignee_name']) ?>
                        <?php else: ?>
                            🧺 up for grabs
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php if ($admin): ?>
                <div class="task-actions">
                    <?php if ($task['status'] === 'completed'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reopen">
                            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                            <button class="btn-ghost btn-sm" type="submit">Reopen</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" data-confirm="Delete this task?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <button class="btn-ghost btn-sm" type="submit">Delete</button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$tasks): ?>
        <p class="empty">🐈 Nothing here. Time for a sunbeam nap.</p>
    <?php endif; ?>
    </div>
</section>
<?php
render_footer();
