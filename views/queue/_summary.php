<?php
$queueOverview = is_array($queueSummary ?? null) ? $queueSummary : [];
$queueOverviewTotal = max(0, (int) ($queueOverview['total'] ?? 0));
$queueOverviewCards = [
    [
        'count' => (int) ($queueOverview['completed'] ?? 0),
        'label' => 'Finalizadas',
        'class' => 'is-completed',
        'show_percent' => true,
    ],
    [
        'count' => (int) ($queueOverview['pending'] ?? 0),
        'label' => 'Pendentes',
        'class' => 'is-pending',
        'show_percent' => true,
    ],
    [
        'count' => (int) ($queueOverview['auditing'] ?? 0),
        'label' => 'Em auditoria',
        'class' => 'is-auditing',
        'show_percent' => true,
    ],
    [
        'count' => (int) ($queueOverview['total'] ?? 0),
        'label' => 'Total na fila',
        'class' => 'is-total',
        'show_percent' => false,
    ],
    [
        'count' => (int) ($queueOverview['vivo_total'] ?? 0),
        'label' => 'Vivo Total',
        'class' => 'is-vivo-total',
        'show_percent' => true,
    ],
    [
        'count' => (int) ($queueOverview['upgrade_total'] ?? 0),
        'label' => 'Upgrade',
        'class' => 'is-upgrade',
        'show_percent' => true,
    ],
];
?>
<section class="panel queue-overview-panel">
    <div class="queue-overview-grid">
        <?php foreach ($queueOverviewCards as $card): ?>
            <?php
            $percentValue = $queueOverviewTotal > 0
                ? (int) round((((int) $card['count']) / $queueOverviewTotal) * 100)
                : 0;
            $progressValue = $card['show_percent']
                ? $percentValue
                : ($queueOverviewTotal > 0 ? 100 : 0);
            ?>
            <article class="queue-overview-card <?= e($card['class']) ?>">
                <span class="queue-overview-count" style="--queue-progress: <?= e((string) $progressValue) ?>;">
                    <span class="queue-overview-count-value"><?= e((string) $card['count']) ?></span>
                </span>
                <div class="queue-overview-copy">
                    <strong><?= e($card['label']) ?></strong>
                    <?php if ($card['show_percent']): ?>
                        <small><?= e((string) $percentValue) ?>%</small>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
