<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$me = require_admin();

function render_health(array $checks): void
{
    $overall = health_overall($checks);
    [$icon, $label] = health_status_label($overall);
    $groups = [];
    foreach ($checks as $check) {
        $groups[$check['group']][] = $check;
    }
    $counts = array_count_values(array_column($checks, 'status'));
    ?>
    <div class="health-banner health-<?= e($overall) ?>">
        <span class="health-dot"><?= $icon ?></span>
        <div>
            <strong><?= e($label === 'Healthy' ? 'All systems purring' : ($overall === 'warn' ? 'Some things need attention' : 'Something is wrong')) ?></strong>
            <div class="small">
                <?= (int) (($counts['ok'] ?? 0) + ($counts['info'] ?? 0)) ?> fine ·
                <?= (int) ($counts['warn'] ?? 0) ?> warnings ·
                <?= (int) ($counts['bad'] ?? 0) ?> problems ·
                checked <?= e(date('g:i:s a')) ?>
            </div>
        </div>
    </div>
    <div class="health-grid">
        <?php foreach ($groups as $group => $items): ?>
            <article class="card">
                <h2><?= e($group) ?></h2>
                <?php foreach ($items as $check):
                    [$ci, $cl] = health_status_label($check['status']); ?>
                    <div class="health-row">
                        <span class="health-pill health-<?= e($check['status']) ?>" title="<?= e($cl) ?>"><?= $ci ?> <?= e($cl) ?></span>
                        <div class="health-text">
                            <strong><?= e($check['name']) ?></strong>
                            <?php if ($check['hint']): ?><span class="muted small"><?= e($check['hint']) ?></span><?php endif; ?>
                        </div>
                        <span class="health-value"><?= e($check['value']) ?></span>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

// Partial refresh for assets/js/dashboard.js.
if (isset($_GET['partial'])) {
    header('Cache-Control: no-store');
    render_health(health_checks());
    exit;
}

render_header('System Health');
?>
<section class="card hero-card">
    <div>
        <h1>🩺 System Health Monitor</h1>
        <p>Live checks on the app, database, storage, security and integrations.</p>
        <p class="muted small">Refreshes every 15 seconds while this tab is open.</p>
    </div>
    <div class="mascot board-mascot"><?= animal_avatar('owl', 'xl') ?></div>
</section>
<section id="health" data-refresh="health.php?partial=1" data-every="15000" style="margin-top:20px;">
    <?php render_health(health_checks()); ?>
</section>
<?php
render_footer(['assets/js/dashboard.js']);
