<?php
$executiveRefreshMs = (int) (($dashboardExecutiveData['refreshSeconds'] ?? 8) * 1000);
?>

<?php if ($dashboardExecutiveAllowed): ?>
    <section class="panel dashboard-mode-panel">
        <div class="dashboard-mode-toolbar">
            <div class="dashboard-mode-copy">
                <p class="eyebrow">Supervis&atilde;o</p>
                <h3><?= $dashboardExecutiveMode ? 'Painel ao vivo ativo' : 'Painel executivo' ?></h3>
                <p>
                    <?= $dashboardExecutiveMode
                        ? 'Modo TV com indicadores operacionais, ranking de backoffice e atualiza&ccedil;&atilde;o em tempo real.'
                        : 'Abra o painel executivo para acompanhar produtividade, receita finalizada e desempenho das opera&ccedil;&otilde;es ao vivo.' ?>
                </p>
            </div>

            <div class="dashboard-mode-actions">
                <?php if ($dashboardExecutiveMode): ?>
                    <a href="<?= e($dashboardExecutiveExitUrl) ?>" class="secondary-button dashboard-mode-button is-active">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7h4M7 17h4M7 7v4M7 17v-4M17 7h-4M17 17h-4M17 7v4M17 17v-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span>Voltar ao resumo</span>
                    </a>
                <?php else: ?>
                    <a href="<?= e($dashboardExecutiveToggleUrl) ?>" class="secondary-button dashboard-mode-button">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 19h16M7 16V9M12 16V5M17 16v-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span>Painel ao vivo</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $dashboardFiltersOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Dashboard</small>
            <strong>Filtros</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $dashboardFiltersOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $dashboardFiltersOpen ? '' : 'hidden' ?>>
        <form method="get" class="filters">
            <input type="hidden" name="route" value="dashboard">
            <?php if ($dashboardExecutiveMode): ?>
                <input type="hidden" name="executive" value="1">
            <?php endif; ?>

            <div
                class="date-range-field"
                data-date-range
                data-initial-start="<?= e($dashboardDateFromFilter ?? '') ?>"
                data-initial-end="<?= e($dashboardDateToFilter ?? '') ?>"
            >
                <span>Data input</span>
                <input type="hidden" name="date_from" value="<?= e($dashboardDateFromFilter ?? '') ?>" data-date-range-start>
                <input type="hidden" name="date_to" value="<?= e($dashboardDateToFilter ?? '') ?>" data-date-range-end>

                <button type="button" class="date-range-trigger" data-date-range-trigger aria-expanded="false">
                    <span data-date-range-label><?= e($dashboardDateRangeLabel) ?></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 2v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8H5v8h14v-8z"></path>
                    </svg>
                </button>

                <div class="date-range-picker" data-date-range-picker hidden>
                    <div class="date-range-head">
                        <button type="button" class="date-range-nav" data-date-range-prev aria-label="M&ecirc;s anterior">
                            <span aria-hidden="true">&#8249;</span>
                        </button>
                        <strong data-date-range-month></strong>
                        <button type="button" class="date-range-nav" data-date-range-next aria-label="Pr&oacute;ximo m&ecirc;s">
                            <span aria-hidden="true">&#8250;</span>
                        </button>
                    </div>

                    <div class="date-range-weekdays">
                        <span>D</span>
                        <span>S</span>
                        <span>T</span>
                        <span>Q</span>
                        <span>Q</span>
                        <span>S</span>
                        <span>S</span>
                    </div>

                    <div class="date-range-grid" data-date-range-grid></div>

                    <div class="date-range-footer">
                        <small class="muted" data-date-range-summary><?= e($dashboardDateRangeLabel) ?></small>
                        <div class="form-actions-right">
                            <button type="button" class="ghost-button small-button" data-date-range-clear>Limpar data</button>
                            <button type="button" class="secondary-button small-button" data-date-range-close>Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

            <label>
                <span>Tipo cliente</span>
                <select name="customer_type">
                    <option value="">Todos</option>
                    <option value="B2C" <?= $dashboardCustomerTypeFilter === 'B2C' ? 'selected' : '' ?>>B2C</option>
                    <option value="B2B" <?= $dashboardCustomerTypeFilter === 'B2B' ? 'selected' : '' ?>>B2B</option>
                </select>
            </label>

            <label>
                <span>Opera&ccedil;&atilde;o</span>
                <select name="operation">
                    <option value="">Todas</option>
                    <?php foreach ($dashboardOperations as $operation): ?>
                        <option value="<?= e($operation) ?>" <?= $dashboardOperationFilter === $operation ? 'selected' : '' ?>>
                            <?= e($operation) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-actions-right">
                <button type="submit" class="secondary-button">Filtrar</button>
                <a href="<?= e($dashboardExecutiveMode ? $dashboardExecutiveExitUrl : url('dashboard')) ?>" class="icon-button" data-ui-tooltip="Limpar filtros" aria-label="Limpar filtros">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
                    </svg>
                </a>
            </div>
        </form>
    </div>
</section>

<div
    id="dashboard-live-root"
    class="dashboard-live-root<?= $dashboardExecutiveMode ? ' is-executive' : '' ?>"
    <?= $dashboardExecutiveMode ? 'data-dashboard-live data-dashboard-live-url="' . e($dashboardLiveUrl) . '" data-dashboard-live-interval="' . e((string) $executiveRefreshMs) . '"' : '' ?>
>
    <?= view('dashboard/_live', [
        'stats' => $stats,
        'recentBatches' => $recentBatches,
        'dashboardExecutiveMode' => $dashboardExecutiveMode,
        'dashboardExecutiveData' => $dashboardExecutiveData,
    ]) ?>
</div>
