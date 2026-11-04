<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$myId = (int) $me['id'];
$admin = is_admin($me);
$pdo = db();
$today = date('Y-m-d');

$colors = ['lemon' => 'Lemon', 'pink' => 'Strawberry', 'mint' => 'Mint', 'sky' => 'Sky', 'lavender' => 'Lavender'];
$reactions = ['paw' => '🐾', 'heart' => '❤️', 'party' => '🎉', 'laugh' => '😹'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'react') {
        $reaction = (string) ($_POST['reaction'] ?? '');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM announcements WHERE id = ?');
        $exists->execute([$id]);
        if (isset($reactions[$reaction]) && (int) $exists->fetchColumn() > 0) {
            $del = $pdo->prepare('DELETE FROM announcement_reactions WHERE announcement_id = ? AND user_id = ? AND reaction = ?');
            $del->execute([$id, $myId, $reaction]);
            if ($del->rowCount() === 0) {
                $pdo->prepare('INSERT INTO announcement_reactions (announcement_id, user_id, reaction) VALUES (?, ?, ?)')
                    ->execute([$id, $myId, $reaction]);
            }
        }
        redirect('board.php#note-' . $id);
    }

    require_admin();

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $color = (string) ($_POST['color'] ?? 'lemon');
        $pinned = !empty($_POST['pinned']) ? 1 : 0;
        $expires = trim((string) ($_POST['expires_on'] ?? ''));
        $expiresDate = $expires !== '' ? DateTime::createFromFormat('!Y-m-d', $expires) : null;

        if ($title === '' || mb_strlen($title) > 120) {
            flash('err', 'Give the announcement a title (up to 120 characters).');
        } elseif ($body === '' || mb_strlen($body) > 1500) {
            flash('err', 'Write a message (up to 1,500 characters).');
        } elseif ($expires !== '' && (!$expiresDate || $expiresDate->format('Y-m-d') < $today)) {
            flash('err', 'The "show until" date needs to be today or later.');
        } else {
            if (!isset($colors[$color])) {
                $color = 'lemon';
            }
            $pdo->prepare('INSERT INTO announcements (title, body, color, pinned, author_id, expires_on) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$title, $body, $color, $pinned, $myId, $expiresDate ? $expiresDate->format('Y-m-d') : null]);
            $newId = (int) $pdo->lastInsertId();
            log_activity($myId, 'announcement_post', 'Posted "' . $title . '"');
            notify_everyone('board', '📌 ' . $title, mb_substr($body, 0, 140), 'board.php#note-' . $newId, $myId);
            flash('ok', 'Announcement pinned to the board.');
        }
        redirect('board.php');
    }

    $stmt = $pdo->prepare('SELECT title FROM announcements WHERE id = ?');
    $stmt->execute([$id]);
    $title = $stmt->fetchColumn();
    if ($title === false) {
        flash('err', 'That note fell off the board.');
        redirect('board.php');
    }

    if ($action === 'pin') {
        $pdo->prepare('UPDATE announcements SET pinned = 1 - pinned WHERE id = ?')->execute([$id]);
        redirect('board.php#note-' . $id);
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        log_activity($myId, 'announcement_delete', 'Removed "' . $title . '"');
        flash('ok', 'Announcement removed.');
        redirect('board.php');
    }

    redirect('board.php');
}

$where = $admin ? '' : 'WHERE a.expires_on IS NULL OR a.expires_on >= ?';
$stmt = $pdo->prepare("
    SELECT a.*, u.name AS author, u.animal, u.cat_avatar
    FROM announcements a LEFT JOIN users u ON u.id = a.author_id
    $where
    ORDER BY a.pinned DESC, a.created_at DESC
    LIMIT 60
");
$stmt->execute($admin ? [] : [$today]);
$notes = $stmt->fetchAll();

// Reaction counts and who reacted, per note.
$counts = [];
if ($notes) {
    $ids = array_column($notes, 'id');
    $stmt = $pdo->prepare('
        SELECT r.announcement_id, r.reaction, r.user_id, u.name
        FROM announcement_reactions r JOIN users u ON u.id = r.user_id
        WHERE r.announcement_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
    ');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['announcement_id']][$row['reaction']][(int) $row['user_id']] = $row['name'];
    }
}

render_header('Announcement Board');
?>
<section class="card hero-card">
    <div>
        <h1>📌 Announcement Board</h1>
        <p>Office news, pinned with tiny paws. React to show you've read it.</p>
    </div>
    <div class="mascot board-mascot"><?= user_avatar($me, 'xl') ?></div>
</section>

<?php if ($admin): ?>
<section class="card" style="margin-top:20px;">
    <h2>Post an announcement</h2>
    <form class="form form-inline" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label class="span-2">Title <input name="title" maxlength="120" placeholder="Pizza Friday is back!" required></label>
        <label>Note colour
            <select name="color">
                <?php foreach ($colors as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Show until <input type="date" name="expires_on" min="<?= e($today) ?>"></label>
        <label class="span-4">Message <textarea name="body" rows="3" maxlength="1500" placeholder="What does the office need to know?" required></textarea></label>
        <label class="check-label"><input type="checkbox" name="pinned" value="1"> Pin to the top</label>
        <span></span><span></span>
        <button type="submit">Post to board</button>
    </form>
    <p class="muted small">Everyone gets a notification. Leave "show until" empty to keep it up indefinitely.</p>
</section>
<?php endif; ?>

<section class="board" style="margin-top:20px;">
    <?php foreach ($notes as $i => $note):
        $expired = $note['expires_on'] !== null && $note['expires_on'] < $today;
        $tilt = [-1.6, 1.2, -0.6, 1.8, -1.2, 0.8][$i % 6];
    ?>
        <article class="note note-<?= e($note['color']) ?> <?= $note['pinned'] ? 'note-pinned' : '' ?> <?= $expired ? 'note-expired' : '' ?>"
                 id="note-<?= (int) $note['id'] ?>" style="--tilt: <?= $tilt ?>deg">
            <span class="note-pin" aria-hidden="true"><?= $note['pinned'] ? '📌' : '🧷' ?></span>
            <h3><?= e($note['title']) ?></h3>
            <p><?= nl2br(e($note['body'])) ?></p>
            <div class="note-meta">
                <?= user_avatar(['animal' => $note['animal'] ?: 'cat', 'cat_avatar' => $note['cat_avatar']], 'xs') ?>
                <span><?= e($note['author'] ?: 'Former teammate') ?> · <?= e(nice_date($note['created_at'])) ?></span>
                <?php if ($note['expires_on']): ?>
                    <span class="chip chip-soft"><?= $expired ? 'expired' : 'until ' . e(nice_date($note['expires_on'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="note-actions">
                <?php foreach ($reactions as $key => $icon):
                    $who = $counts[$note['id']][$key] ?? [];
                    $mine = isset($who[$myId]);
                ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="react">
                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                        <input type="hidden" name="reaction" value="<?= e($key) ?>">
                        <button type="submit" class="react <?= $mine ? 'react-on' : '' ?>" title="<?= e($who ? implode(', ', $who) : 'Be the first!') ?>">
                            <?= $icon ?><?php if ($who): ?> <span><?= count($who) ?></span><?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <?php if ($admin): ?>
                    <span class="tabs-spacer"></span>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="pin">
                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                        <button class="btn-ghost btn-sm" type="submit"><?= $note['pinned'] ? 'Unpin' : 'Pin' ?></button>
                    </form>
                    <form method="post" data-confirm="Remove this announcement?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                        <button class="btn-ghost btn-sm" type="submit" aria-label="Remove">✕</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$notes): ?>
        <p class="empty card">📭 The board is empty. <?= $admin ? 'Post the first announcement above!' : 'Nothing to read yet.' ?></p>
    <?php endif; ?>
</section>
<?php
render_footer();
