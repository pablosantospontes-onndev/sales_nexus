<?php
$executiveData = is_array($dashboardExecutiveData ?? null) ? $dashboardExecutiveData : null;
$executiveKpis = $executiveData['kpis'] ?? [];
$hourlyPeaks = $executiveData['hourlyPeaks'] ?? [];
$topBackoffices = $executiveData['topBackoffices'] ?? [];
$topOperations = $executiveData['topOperations'] ?? [];
$slowestBackoffices = $executiveData['slowestBackoffices'] ?? [];
$latestFinalizations = $executiveData['latestFinalizations'] ?? [];
$peakHour = $executiveData['peakHour'] ?? null;
$onlineOperationalUsers = (int) ($executiveData['onlineOperationalUsers'] ?? 0);
$refreshSeconds = (int) ($executiveData['refreshSeconds'] ?? 8);
$generatedAt = $executiveData['generatedAt'] ?? null;
$maxHourlySales = max(1, ...array_map(static fn (array $row): int => (int) ($row['finalized_sales'] ?? 0), $hourlyPeaks ?: [['finalized_sales' => 0]]));
$maxBackofficeSales = max(1, ...array_map(static fn (array $row): int => (int) ($row['finalized_sales'] ?? 0), $topBackoffices ?: [['finalized_sales' => 0]]));
$maxOperationSales = max(1, ...array_map(static fn (array $row): int => (int) ($row['finalized_sales'] ?? 0), $topOperations ?: [['finalized_sales' => 0]]));
$maxSlowMinutes = max(1, ...array_map(static fn (array $row): int => (int) ($row['average_minutes'] ?? 0), $slowestBackoffices ?: [['average_minutes' => 0]]));
$finalizedComparison = is_array($finalizedComparison ?? null) ? $finalizedComparison : [];
$finalizedDays = $finalizedComparison['days'] ?? [];
$currentPeriodStart = $finalizedComparison['current_start'] ?? null;
$currentPeriodEnd = $finalizedComparison['current_end'] ?? null;
$previousPeriodStart = $finalizedComparison['previous_start'] ?? null;
$previousPeriodEnd = $finalizedComparison['previous_end'] ?? null;
$currentMonthLabel = $finalizedComparison['current_month_label'] ?? '';
$previousMonthLabel = $finalizedComparison['previous_month_label'] ?? '';
?>

<section class="stats-grid">
    <article class="stat-card">
        <span>Total na fila</span>
        <strong><?= e((string) $stats['total']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Pendente input</span>
        <strong><?= e((string) $stats['pending']) ?></strong>
    </article>
    <article class="stat-card<?= (int) ($stats['claimed'] ?? 0) > 0 ? ' is-active-claimed' : '' ?>">
        <span>Em atendimento</span>
        <strong><?= e((string) $stats['claimed']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Finalizadas</span>
        <strong><?= e((string) $stats['completed']) ?></strong>
    </article>
</section>

<?php if ($dashboardExecutiveMode && $executiveData !== null): ?>
    <section class="panel executive-dashboard-panel" id="executive-dashboard-panel">
        <div class="executive-dashboard-head">
            <div>
                <p class="eyebrow">Painel executivo</p>
                <h3>Acompanhamento ao vivo</h3>
                <p>Atualiza&ccedil;&atilde;o autom&aacute;tica a cada <?= e((string) $refreshSeconds) ?>s para opera&ccedil;&atilde;o, supervis&atilde;o e exibi&ccedil;&atilde;o em TV.</p>
            </div>

            <div class="executive-dashboard-actions">
                <span class="executive-refresh-badge">Atualizado &agrave;s <?= e(format_datetime_br($generatedAt)) ?></span>
                <button type="button" class="ghost-button small-button executive-fullscreen-button" data-dashboard-fullscreen-target="dashboard-live-root">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8 4H4v4M20 8V4h-4M4 16v4h4M16 20h4v-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Tela cheia</span>
                </button>
            </div>
        </div>

        <div class="executive-kpi-grid">
            <article class="executive-kpi-card is-highlight">
                <small>Receita finalizada</small>
                <strong><?= e(format_currency_br((float) ($executiveKpis['revenue_total'] ?? 0))) ?></strong>
                <span>Base: pontua&ccedil;&atilde;o comercial dos produtos finalizados.</span>
            </article>

            <article class="executive-kpi-card">
                <small>Ticket m&eacute;dio</small>
                <strong><?= e(format_currency_br((float) ($executiveKpis['average_revenue'] ?? 0))) ?></strong>
                <span><?= e((string) ((int) ($executiveKpis['finalized_sales'] ?? 0))) ?> vendas conclu&iacute;das no recorte atual.</span>
            </article>

            <article class="executive-kpi-card">
                <small>Tempo m&eacute;dio</small>
                <strong><?= e(format_minutes_human((float) ($executiveKpis['average_minutes'] ?? 0))) ?></strong>
                <span>Tempo entre pegar a venda e concluir a auditoria.</span>
            </article>

            <article class="executive-kpi-card<?= $onlineOperationalUsers > 0 ? ' is-online' : '' ?>">
                <small>Backoffices online</small>
                <strong><?= e((string) $onlineOperationalUsers) ?></strong>
                <span>Usu&aacute;rios com sess&atilde;o ativa entre backoffice e supervisor.</span>
            </article>

            <article class="executive-kpi-card">
                <small>Pico de finaliza&ccedil;&atilde;o</small>
                <strong><?= e(($peakHour['label'] ?? '--') . ' / ' . (string) ((int) ($peakHour['finalized_sales'] ?? 0))) ?></strong>
                <span>Hor&aacute;rio com maior volume de vendas finalizadas.</span>
            </article>
        </div>

        <div class="executive-main-grid">
            <section class="executive-card executive-hourly-card">
                <div class="executive-card-head">
                    <div>
                        <p class="eyebrow">Pico por hora</p>
                        <h4>Finaliza&ccedil;&otilde;es por hor&aacute;rio</h4>
                    </div>
                    <span class="executive-card-note">08h &agrave;s 22h</span>
                </div>

                <?php if ($hourlyPeaks === []): ?>
                    <div class="empty-state">Nenhuma finaliza&ccedil;&atilde;o encontrada para o recorte atual.</div>
                <?php else: ?>
                    <div class="executive-hourly-chart">
                        <?php foreach ($hourlyPeaks as $hourData): ?>
                            <?php
                            $hourlyHeight = max(6, (int) round((((int) ($hourData['finalized_sales'] ?? 0)) / $maxHourlySales) * 100));
                            $isPeakHour = ($peakHour['hour_number'] ?? -1) === ($hourData['hour_number'] ?? -2) && (int) ($hourData['finalized_sales'] ?? 0) > 0;
                            ?>
                            <div class="executive-hourly-bar<?= $isPeakHour ? ' is-peak' : '' ?>" data-ui-tooltip="<?= e(($hourData['label'] ?? '--') . ': ' . (string) ((int) ($hourData['finalized_sales'] ?? 0)) . ' finalizadas') ?>">
                                <span class="executive-hourly-value"><?= e((string) ((int) ($hourData['finalized_sales'] ?? 0))) ?></span>
                                <span class="executive-hourly-column" style="--bar-height: <?= e((string) $hourlyHeight) ?>%"></span>
                                <small><?= e($hourData['label'] ?? '--') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="executive-card">
                <div class="executive-card-head">
                    <div>
                        <p class="eyebrow">Ranking</p>
                        <h4>Melhores backoffices</h4>
                    </div>
                    <span class="executive-card-note">Mais finaliza&ccedil;&otilde;es</span>
                </div>

                <?php if ($topBackoffices === []): ?>
                    <div class="empty-state">Nenhum backoffice com finaliza&ccedil;&otilde;es no recorte atual.</div>
                <?php else: ?>
                    <div class="executive-ranking-list">
                        <?php foreach ($topBackoffices as $index => $item): ?>
                            <article class="executive-ranking-item">
                                <span class="executive-ranking-place"><?= e((string) ($index + 1)) ?></span>
                                <div class="executive-ranking-copy">
                                    <strong><?= e($item['name']) ?></strong>
                                    <small><?= e((string) $item['finalized_sales']) ?> finalizadas | <?= e(format_currency_br((float) $item['revenue_total'])) ?></small>
                                </div>
                                <div class="executive-ranking-meter">
                                    <span style="width: <?= e((string) max(12, (int) round((((int) $item['finalized_sales']) / $maxBackofficeSales) * 100))) ?>%"></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="executive-secondary-grid">
            <section class="executive-card">
                <div class="executive-card-head">
                    <div>
                        <p class="eyebrow">Opera&ccedil;&otilde;es</p>
                        <h4>Mais vendas finalizadas</h4>
                    </div>
                    <span class="executive-card-note">Top opera&ccedil;&otilde;es</span>
                </div>

                <?php if ($topOperations === []): ?>
                    <div class="empty-state">Nenhuma opera&ccedil;&atilde;o com vendas finalizadas neste recorte.</div>
                <?php else: ?>
                    <div class="executive-ranking-list">
                        <?php foreach ($topOperations as $index => $item): ?>
                            <article class="executive-ranking-item">
                                <span class="executive-ranking-place"><?= e((string) ($index + 1)) ?></span>
                                <div class="executive-ranking-copy">
                                    <strong><?= e($item['name']) ?></strong>
                                    <small><?= e((string) $item['finalized_sales']) ?> finalizadas | <?= e(format_currency_br((float) $item['revenue_total'])) ?></small>
                                </div>
                                <div class="executive-ranking-meter">
                                    <span style="width: <?= e((string) max(12, (int) round((((int) $item['finalized_sales']) / $maxOperationSales) * 100))) ?>%"></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="executive-card">
                <div class="executive-card-head">
                    <div>
                        <p class="eyebrow">Produtividade</p>
                        <h4>Maiores tempos m&eacute;dios</h4>
                    </div>
                    <span class="executive-card-note">Do claim at&eacute; a finaliza&ccedil;&atilde;o</span>
                </div>

                <?php if ($slowestBackoffices === []): ?>
                    <div class="empty-state">Ainda n&atilde;o h&aacute; tempo suficiente de hist&oacute;rico para este ranking.</div>
                <?php else: ?>
                    <div class="executive-slowest-list">
                        <?php foreach ($slowestBackoffices as $item): ?>
                            <article class="executive-slowest-item">
                                <div>
                                    <strong><?= e($item['name']) ?></strong>
                                    <small><?= e((string) $item['finalized_sales']) ?> finalizadas | pico <?= e(format_minutes_human((float) $item['longest_minutes'])) ?></small>
                                </div>
                                <div class="executive-slowest-metric">
                                    <span><?= e(format_minutes_human((float) $item['average_minutes'])) ?></span>
                                    <div class="executive-slowest-meter">
                                        <span style="width: <?= e((string) max(12, (int) round((((int) $item['average_minutes']) / $maxSlowMinutes) * 100))) ?>%"></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="executive-card executive-latest-card">
            <div class="executive-card-head">
                <div>
                    <p class="eyebrow">Tempo real</p>
                    <h4>&Uacute;ltimas finaliza&ccedil;&otilde;es</h4>
                </div>
                <span class="executive-card-note">Fila viva</span>
            </div>

            <?php if ($latestFinalizations === []): ?>
                <div class="empty-state">Nenhuma venda finalizada encontrada para o painel ao vivo.</div>
            <?php else: ?>
                <div class="executive-latest-list">
                    <?php foreach ($latestFinalizations as $item): ?>
                        <?php $papCodeShort = substr((string) $item['pap_code'], 0, 10); ?>
                        <article class="executive-latest-item">
                            <div class="executive-latest-main">
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">Cliente:</span>
                                    <span class="executive-latest-value executive-latest-customer"><?= e($item['customer_name']) ?></span>
                                </div>
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">C&oacute;digo da venda:</span>
                                    <span class="executive-latest-value executive-latest-code"><?= e($papCodeShort) ?></span>
                                </div>
                            </div>
                            <div class="executive-latest-meta">
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">Finalizado por:</span>
                                    <span class="executive-latest-value"><?= e($item['auditor_name']) ?></span>
                                </div>
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">Opera&ccedil;&atilde;o:</span>
                                    <span class="executive-latest-value"><?= e($item['operation_name']) ?></span>
                                </div>
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">Base grupo:</span>
                                    <span class="executive-latest-value"><?= e($item['base_group_name']) ?></span>
                                </div>
                                <div class="executive-latest-line">
                                    <span class="executive-latest-label">Finalizada em:</span>
                                    <span class="executive-latest-value"><?= e(format_datetime_br($item['finalized_at'])) ?></span>
                                </div>
                            </div>
                            <div class="executive-latest-side">
                                <strong><?= e(format_currency_br((float) $item['revenue'])) ?></strong>
                                <small><?= e($item['duration_minutes'] !== null ? format_minutes_human((float) $item['duration_minutes']) : '-') ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
<?php else: ?>
    <?php
    $comparisonMax = 1;
    $currentTotal = 0;
    $previousTotal = 0;

    foreach ($finalizedDays as $day) {
        $currentCount = (int) ($day['current_count'] ?? 0);
        $previousCount = (int) ($day['previous_count'] ?? 0);
        $currentTotal += $currentCount;
        $previousTotal += $previousCount;
        $comparisonMax = max($comparisonMax, $currentCount, $previousCount);
    }

    $currentRangeLabel = $currentPeriodStart !== null && $currentPeriodEnd !== null
        ? format_date_br($currentPeriodStart) . ' ate ' . format_date_br($currentPeriodEnd)
        : 'Sem intervalo definido';
    $previousRangeLabel = $previousPeriodStart !== null && $previousPeriodEnd !== null
        ? format_date_br($previousPeriodStart) . ' ate ' . format_date_br($previousPeriodEnd)
        : 'Sem intervalo definido';
    $deltaValue = $currentTotal - $previousTotal;
    $deltaPercent = $previousTotal > 0 ? (int) round(($deltaValue / $previousTotal) * 100) : null;
    ?>

    <section class="panel dashboard-weekly-panel">
        <div class="weekly-comparison-head">
            <div>
                <p class="eyebrow">Comparativo mensal</p>
                <h3>Finalizadas na semana atual do m&ecirc;s</h3>
                <small class="muted">Atual: <?= e($currentRangeLabel) ?></small>
                <small class="muted">M&ecirc;s anterior: <?= e($previousRangeLabel) ?></small>
                <small class="muted">- OBS: Domingos destacados em vermelho</small>
            </div>

            <div class="weekly-comparison-totals">
                <div class="weekly-comparison-total is-current">
                    <small>Finalizadas (<?= e($currentMonthLabel) ?>)</small>
                    <strong><?= e((string) $currentTotal) ?></strong>
                </div>
                <div class="weekly-comparison-total is-previous">
                    <small>Mesmo per&iacute;odo (<?= e($previousMonthLabel) ?>)</small>
                    <strong><?= e((string) $previousTotal) ?></strong>
                    <?php if ($deltaPercent !== null): ?>
                        <span class="weekly-comparison-delta<?= $deltaValue < 0 ? ' is-negative' : ' is-positive' ?>">
                            <?= $deltaValue >= 0 ? '+' : '' ?><?= e((string) $deltaPercent) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="weekly-comparison-legend">
            <span><i class="weekly-comparison-swatch is-current"></i><?= e($currentMonthLabel) ?></span>
            <span><i class="weekly-comparison-swatch is-previous"></i><?= e($previousMonthLabel) ?></span>
            <span><i class="weekly-comparison-swatch is-sunday"></i>Domingo</span>
        </div>

        <?php if ($finalizedDays === []): ?>
            <div class="empty-state">Nenhuma venda finalizada no per&iacute;odo selecionado.</div>
        <?php else: ?>
            <div class="weekly-comparison-chart">
                <?php foreach ($finalizedDays as $day): ?>
                    <?php
                    $currentCount = (int) ($day['current_count'] ?? 0);
                    $previousCount = (int) ($day['previous_count'] ?? 0);
                    $currentHeight = $currentCount > 0 ? max(6, (int) round(($currentCount / $comparisonMax) * 100)) : 0;
                    $previousHeight = $previousCount > 0 ? max(6, (int) round(($previousCount / $comparisonMax) * 100)) : 0;
                    $currentIsSunday = (bool) ($day['current_is_sunday'] ?? false);
                    $previousIsSunday = (bool) ($day['previous_is_sunday'] ?? false);
                    $dayLabel = '-';
                    try {
                        $dayLabel = (new DateTimeImmutable((string) ($day['current_date'] ?? '')))->format('d/m');
                    } catch (Throwable) {
                        $dayLabel = (string) ($day['current_date'] ?? '-');
                    }
                    ?>
                    <div class="weekly-comparison-day" data-ui-tooltip="<?= e($currentMonthLabel . ': ' . $currentCount . ' | ' . $previousMonthLabel . ': ' . $previousCount) ?>">
                        <div class="weekly-comparison-card">
                            <div class="weekly-comparison-metrics">
                                <span class="weekly-comparison-metric is-current<?= $currentIsSunday ? ' is-sunday' : '' ?>">
                                    <small>Atual</small>
                                    <strong><?= e((string) $currentCount) ?></strong>
                                </span>
                                <span class="weekly-comparison-metric is-previous<?= $previousIsSunday ? ' is-sunday' : '' ?>">
                                    <small>Anterior</small>
                                    <strong><?= e((string) $previousCount) ?></strong>
                                </span>
                            </div>
                            <div class="weekly-comparison-bars">
                                <span class="weekly-comparison-bar is-current<?= $currentIsSunday ? ' is-sunday' : '' ?>" style="--bar-height: <?= e((string) $currentHeight) ?>"></span>
                                <span class="weekly-comparison-bar is-previous<?= $previousIsSunday ? ' is-sunday' : '' ?>" style="--bar-height: <?= e((string) $previousHeight) ?>"></span>
                            </div>
                        </div>
                        <small class="weekly-comparison-label<?= $currentIsSunday ? ' is-sunday' : '' ?>"><?= e($dayLabel) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
