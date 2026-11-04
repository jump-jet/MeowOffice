<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();
$kinds = event_kinds();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_admin();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $kind = (string) ($_POST['kind'] ?? 'event');
        $date = (string) ($_POST['date'] ?? '');
        $time = (string) ($_POST['time'] ?? '09:00');
        $endTime = trim((string) ($_POST['end_time'] ?? ''));

        $start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        $end = $endTime !== '' ? DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $endTime) : null;

        if ($title === '' || mb_strlen($title) > 120) {
            flash('err', 'Give the entry a title (up to 120 characters).');
        } elseif (!isset($kinds[$kind])) {
            flash('err', 'Pick a type for the entry.');
        } elseif (!$start || $start->format('Y-m-d H:i') !== $date . ' ' . $time) {
            flash('err', 'That date or time looks like a hairball.');
        } elseif ($endTime !== '' && (!$end || $end <= $start)) {
            flash('err', 'The end time needs to be after the start time.');
        } else {
            $pdo->prepare('INSERT INTO events (title, details, kind, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([
                    $title,
                    $details === '' ? null : mb_substr($details, 0, 500),
                    $kind,
                    $start->format('Y-m-d H:i:s'),
                    $end ? $end->format('Y-m-d H:i:s') : null,
                    $myId,
                ]);
            $label = $kinds[$kind][1];
            $when = nice_date($start->format('Y-m-d')) . ' ' . $start->format('g:ia');
            log_activity($myId, 'event_create', "Added $label \"$title\"");
            $link = 'calendar.php?m=' . $start->format('Y-m');
            if ($kind === 'announcement' || $kind === 'maintenance') {
                notify_everyone('event', $kinds[$kind][0] . ' ' . $title, "$label · $when", $link, $myId);
            } else {
                notify_admins('admin', $me['name'] . " added a $label", "\"$title\" · $when", $link, $myId);
            }
            sync_achievements($me);
            flash('ok', "$label added to the calendar.");
            redirect($link);
        }
        redirect('calendar.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT title FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $title = $stmt->fetchColumn();
        if ($title !== false) {
            $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            log_activity($myId, 'event_delete', 'Removed "' . $title . '"');
            flash('ok', 'Entry removed.');
        }
        redirect('calendar.php');
    }

    redirect('calendar.php');
}

// ---- Month grid ------------------------------------------------------------

$month = (string) ($_GET['m'] ?? date('Y-m'));
$first = DateTime::createFromFormat('!Y-m-d', $month . '-01');
if (!$first) {
    $first = new DateTime('first day of this month 00:00');
}
$monthStart = $first->format('Y-m-d');
$monthEnd = (clone $first)->modify('last day of this month')->format('Y-m-d');
$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');

// Admin reminders are private to admins.
$visibility = $admin ? '' : " AND kind <> 'reminder'";

$stmt = $pdo->prepare("SELECT * FROM events WHERE starts_at >= ? AND starts_at < ? + INTERVAL 1 DAY $visibility ORDER BY starts_at");
$stmt->execute([$monthStart, $monthEnd]);
$byDay = [];
foreach ($stmt->fetchAll() as $event) {
    $byDay[substr($event['starts_at'], 0, 10)][] = $event;
}

$stmt = $pdo->prepare("
    SELECT e.*, u.name AS author
    FROM events e LEFT JOIN users u ON u.id = e.created_by
    WHERE e.starts_at >= ? $visibility
    ORDER BY e.starts_at
    LIMIT 12
");
$stmt->execute([date('Y-m-d 00:00:00')]);
$upcoming = $stmt->fetchAll();

$leading = (int) $first->format('N') - 1; // Monday-first grid
$daysInMonth = (int) $first->format('t');
$today = date('Y-m-d');

render_header('Calendar');
?>
<section class="grid grid-cal">
    <article class="card">
        <div class="cal-head">
            <a class="btn btn-ghost btn-sm" href="?m=<?= e($prev) ?>">‹</a>
            <h1><?= e($first->format('F Y')) ?></h1>
            <a class="btn btn-ghost btn-sm" href="?m=<?= e($next) ?>">›</a>
            <a class="btn btn-ghost btn-sm" href="calendar.php">Today</a>
        </div>
        <div class="cal-grid">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?>
                <div class="cal-dow"><?= $dow ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $leading; $i++): ?>
                <div class="cal-day cal-blank"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $date = $first->format('Y-m-') . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                $events = $byDay[$date] ?? [];
            ?>
                <div class="cal-day <?= $date === $today ? 'cal-today' : '' ?>">
                    <span class="cal-num"><?= $d ?></span>
                    <?php foreach (array_slice($events, 0, 3) as $event): ?>
                        <span class="cal-pill cal-<?= e($event['kind']) ?>" title="<?= e(date('g:ia', strtotime($event['starts_at'])) . ' ' . $event['title']) ?>">
                            <?= $kinds[$event['kind']][0] ?> <?= e($event['title']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($events) > 3): ?>
                        <span class="muted small">+<?= count($events) - 3 ?> more</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="cal-legend">
            <?php foreach ($kinds as $key => [$icon, $label]):
                if ($key === 'reminder' && !$admin) {
                    continue;
                } ?>
                <span class="cal-pill cal-<?= e($key) ?>"><?= $icon ?> <?= e($label) ?></span>
            <?php endforeach; ?>
        </div>
    </article>

    <aside class="stack">
        <?php if ($admin): ?>
        <article class="card">
            <h2>Add to calendar</h2>
            <form class="form" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <label>Title <input name="title" maxlength="120" placeholder="Friday treat party" required></label>
                <label>Type
                    <select name="kind">
                        <?php foreach ($kinds as $key => [$icon, $label]): ?>
                            <option value="<?= e($key) ?>"><?= $icon ?> <?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Date <input type="date" name="date" value="<?= e($today) ?>" required></label>
                <div class="form-row">
                    <label>Starts <input type="time" name="time" value="09:00" required></label>
                    <label>Ends <input type="time" name="end_time"></label>
                </div>
                <label>Details <input name="details" maxlength="500" placeholder="Optional"></label>
                <button type="submit">Add entry</button>
            </form>
            <p class="muted small">Announcements and maintenance notify everyone. Reminders are only visible to admins.</p>
        </article>
        <?php endif; ?>

        <article class="card">
            <h2>Coming up</h2>
            <?php foreach ($upcoming as $event): ?>
                <div class="event-row event-<?= e($event['kind']) ?>">
                    <span class="event-icon"><?= $kinds[$event['kind']][0] ?></span>
                    <div>
                        <strong><?= e($event['title']) ?></strong>
                        <div class="muted small">
                            <?= e(nice_date($event['starts_at'])) ?> · <?= e(date('g:ia', strtotime($event['starts_at']))) ?><?= $event['ends_at'] ? '–' . e(date('g:ia', strtotime($event['ends_at']))) : '' ?>
                            · <?= e($kinds[$event['kind']][1]) ?>
                        </div>
                        <?php if ($event['details']): ?><div class="small"><?= e($event['details']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($admin): ?>
                        <form method="post" data-confirm="Remove this entry?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                            <button class="btn-ghost btn-sm" type="submit" aria-label="Remove">✕</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?>
                <p class="empty">📭 Nothing planned. Suspiciously calm.</p>
            <?php endif; ?>
        </article>
    </aside>
</section>
<?php
render_footer();
