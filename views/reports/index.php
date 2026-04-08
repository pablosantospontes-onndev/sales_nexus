<?php
$reportsFilters = $reportsFilters ?? [];
$reportsOptions = $reportsOptions ?? [];
$reportsYears = $reportsYears ?? [];
$reportsOverview = $reportsOverview ?? [];
$reportsByOperation = $reportsByOperation ?? [];
$reportsByBaseGroup = $reportsByBaseGroup ?? [];
$reportsRows = $reportsRows ?? [];
$reportsPage = (int) ($reportsPage ?? 1);
$reportsTotalPages = (int) ($reportsTotalPages ?? 1);
$reportsTotal = (int) ($reportsTotal ?? 0);
$reportsUpdatedAt = $reportsUpdatedAt ?? null;
$reportsQueryParams = $reportsQueryParams ?? [];

$selectedYear = (int) ($reportsFilters['year'] ?? (int) date('Y'));
$selectedMonths = array_values(array_filter(array_map(
    static fn (mixed $month): string => preg_match('/^(0[1-9]|1[0-2])$/', (string) $month) ? (string) $month : '',
    (array) ($reportsFilters['months'] ?? $reportsFilters['month'] ?? [])
), static fn (string $month): bool => $month !== ''));
$selectedDay = (string) ($reportsFilters['day'] ?? '');
$selectedStatuses = (array) ($reportsFilters['statuses'] ?? []);
$selectedSubStatuses = (array) ($reportsFilters['sub_statuses'] ?? []);
$selectedOperations = (array) ($reportsFilters['operations'] ?? []);
$selectedBaseGroups = (array) ($reportsFilters['base_groups'] ?? []);
$selectedCustomerTypes = (array) ($reportsFilters['customer_types'] ?? []);
$selectedSupervisors = (array) ($reportsFilters['supervisors'] ?? []);
$selectedCoordinators = (array) ($reportsFilters['coordinators'] ?? []);
$selectedManagers = (array) ($reportsFilters['managers'] ?? []);
$selectedConsultants = (array) ($reportsFilters['consultants'] ?? []);
$selectedTerritories = (array) ($reportsFilters['territories'] ?? []);
$termFilter = (string) ($reportsFilters['term'] ?? '');

$reportsCards = [
    ['label' => 'Receita gerencial', 'value' => (float) ($reportsOverview['managerial_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Pontua&ccedil;&atilde;o comercial', 'value' => (float) ($reportsOverview['commercial_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Receita ativo', 'value' => (float) ($reportsOverview['managerial_active_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Pontua&ccedil;&atilde;o ativo', 'value' => (float) ($reportsOverview['commercial_active_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Receita Cancelada', 'value' => (float) ($reportsOverview['managerial_canceled_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Pontua&ccedil;&atilde;o Cancelada', 'value' => (float) ($reportsOverview['commercial_canceled_total'] ?? 0), 'type' => 'currency', 'wide' => true],
    ['label' => 'Ticket m&eacute;dio', 'value' => (float) ($reportsOverview['ticket_average'] ?? 0), 'type' => 'currency', 'class' => 'is-ticket-average'],
    ['label' => 'FTTH B2C', 'value' => (int) ($reportsOverview['ftth_b2c_sales'] ?? 0), 'type' => 'count'],
    ['label' => 'FTTH B2B', 'value' => (int) ($reportsOverview['ftth_b2b_sales'] ?? 0), 'type' => 'count'],
    ['label' => 'FTTH TOTAL', 'value' => (int) ($reportsOverview['ftth_total_sales'] ?? 0), 'type' => 'count'],
];

$reportsMonths = [
    '01' => 'Jan',
    '02' => 'Fev',
    '03' => 'Mar',
    '04' => 'Abr',
    '05' => 'Mai',
    '06' => 'Jun',
    '07' => 'Jul',
    '08' => 'Ago',
    '09' => 'Set',
    '10' => 'Out',
    '11' => 'Nov',
    '12' => 'Dez',
];
?>

<section class="panel reports-shell">
    <div class="reports-toolbar">
        <div>
            <p class="eyebrow">Relat&oacute;rios</p>
            <h3>Painel anal&iacute;tico de vendas finalizadas</h3>
            <p class="muted">Vis&atilde;o consolidada conforme os filtros ativos.</p>
        </div>

        <div class="reports-updated-at">
            <span>Dados at&eacute;</span>
            <strong><?= $reportsUpdatedAt ? e(format_datetime_br((string) $reportsUpdatedAt)) : 'Sem dados' ?></strong>
        </div>
    </div>
</section>

<section class="reports-stat-grid">
    <?php foreach ($reportsCards as $card): ?>
        <article class="stat-card reports-stat-card<?= ! empty($card['wide']) ? ' reports-stat-card-wide' : '' ?><?= ! empty($card['class']) ? ' ' . e($card['class']) : '' ?>">
            <span><?= $card['label'] ?></span>
            <strong>
                <?php if ($card['type'] === 'currency'): ?>
                    <?= e(format_currency_br((float) $card['value'])) ?>
                <?php else: ?>
                    <?= e((string) $card['value']) ?>
                <?php endif; ?>
            </strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel reports-filters-panel">
    <div class="reports-filter-head">
        <div>
            <p class="eyebrow">Filtros</p>
            <h3>Refinar relat&oacute;rio</h3>
        </div>
    </div>

    <form method="get" action="index.php" class="reports-filters-form" data-auto-submit-form>
        <input type="hidden" name="route" value="reports">
        <div class="reports-filter-layout">
            <section class="reports-calendar-panel">
                <div class="reports-calendar-group">
                    <span class="reports-calendar-title">Ano</span>
                    <div class="reports-calendar-grid reports-calendar-grid-years">
                        <?php foreach ($reportsYears as $yearValue): ?>
                            <label class="reports-calendar-option<?= $selectedYear === (int) $yearValue ? ' is-active' : '' ?>">
                                <input type="radio" name="year" value="<?= e((string) $yearValue) ?>" <?= $selectedYear === (int) $yearValue ? 'checked' : '' ?>>
                                <span><?= e((string) $yearValue) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="reports-calendar-group">
                    <span class="reports-calendar-title">M&ecirc;s</span>
                    <div class="queue-status-filter reports-checklist-filter reports-calendar-checklist" data-reports-checklist-filter data-default-label="Todos">
                        <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                            <span data-reports-checklist-summary>Todos</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </button>
                        <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                            <?php foreach ($reportsMonths as $monthValue => $monthLabel): ?>
                                <label class="queue-status-option">
                                    <input
                                        type="checkbox"
                                        name="month[]"
                                        value="<?= e($monthValue) ?>"
                                        data-label="<?= e($monthLabel) ?>"
                                        data-reports-checklist-checkbox
                                        <?= in_array($monthValue, $selectedMonths, true) ? 'checked' : '' ?>
                                    >
                                    <span><?= e($monthLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="reports-calendar-group">
                    <span class="reports-calendar-title">Dia</span>
                    <div class="reports-calendar-grid reports-calendar-grid-days">
                        <label class="reports-calendar-option<?= $selectedDay === '' ? ' is-active' : '' ?>">
                            <input type="radio" name="day" value="" <?= $selectedDay === '' ? 'checked' : '' ?>>
                            <span>Todos</span>
                        </label>
                        <?php for ($day = 1; $day <= 31; $day += 1): ?>
                            <?php $dayValue = str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
                            <label class="reports-calendar-option<?= $selectedDay === $dayValue ? ' is-active' : '' ?>">
                                <input type="radio" name="day" value="<?= e($dayValue) ?>" <?= $selectedDay === $dayValue ? 'checked' : '' ?>>
                                <span><?= e($dayValue) ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>

            <section class="reports-filter-grid reports-filter-grid-report">
                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Status</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['statuses'] ?? []) as $statusOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="status[]" value="<?= e($statusOption) ?>" data-reports-checklist-checkbox <?= in_array($statusOption, $selectedStatuses, true) ? 'checked' : '' ?>>
                                <span><?= e($statusOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Sub status</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['sub_statuses'] ?? []) as $subStatusOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="sub_status[]" value="<?= e($subStatusOption) ?>" data-reports-checklist-checkbox <?= in_array($subStatusOption, $selectedSubStatuses, true) ? 'checked' : '' ?>>
                                <span><?= e($subStatusOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todas">
                    <span>Opera&ccedil;&atilde;o</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todas</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['operations'] ?? []) as $operationOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="operation[]" value="<?= e($operationOption) ?>" data-reports-checklist-checkbox <?= in_array($operationOption, $selectedOperations, true) ? 'checked' : '' ?>>
                                <span><?= e($operationOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Base grupo</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['base_groups'] ?? []) as $baseGroupOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="base_group[]" value="<?= e($baseGroupOption) ?>" data-reports-checklist-checkbox <?= in_array($baseGroupOption, $selectedBaseGroups, true) ? 'checked' : '' ?>>
                                <span><?= e($baseGroupOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Tipo cliente</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['customer_types'] ?? []) as $customerTypeOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="customer_type[]" value="<?= e($customerTypeOption) ?>" data-reports-checklist-checkbox <?= in_array($customerTypeOption, $selectedCustomerTypes, true) ? 'checked' : '' ?>>
                                <span><?= e($customerTypeOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Gerente base</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['managers'] ?? []) as $managerOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="manager[]" value="<?= e($managerOption) ?>" data-reports-checklist-checkbox <?= in_array($managerOption, $selectedManagers, true) ? 'checked' : '' ?>>
                                <span><?= e($managerOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Coordenador</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['coordinators'] ?? []) as $coordinatorOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="coordinator[]" value="<?= e($coordinatorOption) ?>" data-reports-checklist-checkbox <?= in_array($coordinatorOption, $selectedCoordinators, true) ? 'checked' : '' ?>>
                                <span><?= e($coordinatorOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Supervisor</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['supervisors'] ?? []) as $supervisorOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="supervisor[]" value="<?= e($supervisorOption) ?>" data-reports-checklist-checkbox <?= in_array($supervisorOption, $selectedSupervisors, true) ? 'checked' : '' ?>>
                                <span><?= e($supervisorOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Consultor</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['consultants'] ?? []) as $consultantOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="consultant[]" value="<?= e($consultantOption) ?>" data-reports-checklist-checkbox <?= in_array($consultantOption, $selectedConsultants, true) ? 'checked' : '' ?>>
                                <span><?= e($consultantOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="Todos">
                    <span>Territ&oacute;rio</span>
                    <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                        <span data-reports-checklist-summary>Todos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                        <?php foreach (($reportsOptions['territories'] ?? []) as $territoryOption): ?>
                            <label class="queue-status-option">
                                <input type="checkbox" name="territory[]" value="<?= e($territoryOption) ?>" data-reports-checklist-checkbox <?= in_array($territoryOption, $selectedTerritories, true) ? 'checked' : '' ?>>
                                <span><?= e($territoryOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="reports-search-field">
                    <span>Pesquisar</span>
                    <div class="reports-search-input">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="reports-search-icon">
                            <path d="M10.5 4.25a6.25 6.25 0 1 1 0 12.5 6.25 6.25 0 0 1 0-12.5Zm0 1.5a4.75 4.75 0 1 0 0 9.5 4.75 4.75 0 0 0 0-9.5Zm7.1 10.35 3.1 3.1a.75.75 0 1 1-1.06 1.06l-3.1-3.1a.75.75 0 0 1 1.06-1.06Z" fill="currentColor"></path>
                        </svg>
                        <input
                            type="text"
                            name="term"
                            value="<?= e($termFilter) ?>"
                            placeholder="Cliente, c&oacute;digo da venda (PAP) ou ordem de servi&ccedil;o"
                        >
                    </div>
                </div>

                <div class="reports-filter-actions">
                    <button type="submit" name="apply" value="1" class="secondary-button reports-action-button">Atualizar</button>
                    <a href="<?= e(url('reports', ['clear' => 1])) ?>" class="icon-button" data-ui-tooltip="Limpar filtros" aria-label="Limpar filtros">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
                        </svg>
                    </a>
                    <a href="<?= e(url('reports/download', $reportsQueryParams)) ?>" class="product-download-button reports-download-button">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.25h3.75L20.5 8v10.75A2.25 2.25 0 0 1 18.25 21h-10A2.25 2.25 0 0 1 6 18.75v-13.5A2.25 2.25 0 0 1 8.25 3h3.5Zm.75 1.5v3.5h3.5L12.75 4.75Zm-2.5 7.5V9.75h1.5v2.5h2.5v1.5h-2.5v2.5h-1.5v-2.5h-2.5v-1.5h2.5Z" fill="currentColor"></path></svg>
                        <span>Download</span>
                    </a>
                </div>
            </section>
        </div>
    </form>
</section>

<section class="reports-panels-grid">
    <article class="panel reports-panel">
        <div class="panel-head reports-panel-head">
            <div>
                <p class="eyebrow">Opera&ccedil;&otilde;es</p>
                <h3>Resumo por opera&ccedil;&atilde;o</h3>
            </div>
            <button
                type="button"
                class="icon-button reports-panel-export-button"
                data-table-export-button
                data-export-table="#reports-operation-summary-table"
                data-export-filename="resumo_operacao"
                data-ui-tooltip="Baixar resumo por opera&ccedil;&atilde;o"
                aria-label="Baixar resumo por opera&ccedil;&atilde;o"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3.75v9.5m0 0 3.75-3.75M12 13.25 8.25 9.5M5.75 18.25h12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>

        <div class="reports-table-wrap">
            <table id="reports-operation-summary-table" class="reports-summary-table">
                <thead>
                    <tr>
                        <th>Opera&ccedil;&atilde;o</th>
                        <th>FTTH B2C</th>
                        <th>FTTH B2B</th>
                        <th>Pontua&ccedil;&atilde;o</th>
                        <th>Receita</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reportsByOperation === []): ?>
                        <tr>
                            <td colspan="5" class="table-empty-cell">Nenhum dado encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportsByOperation as $row): ?>
                            <tr>
                                <td><?= e($row['operation_name'] ?? '-') ?></td>
                                <td><?= e((string) ((int) ($row['ftth_b2c_sales'] ?? 0))) ?></td>
                                <td><?= e((string) ((int) ($row['ftth_b2b_sales'] ?? 0))) ?></td>
                                <td><?= e(format_currency_br((float) ($row['commercial_total'] ?? 0))) ?></td>
                                <td><?= e(format_currency_br((float) ($row['managerial_total'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="panel reports-panel">
        <div class="panel-head reports-panel-head">
            <div>
                <p class="eyebrow">Base grupos</p>
                <h3>Resumo por base grupo</h3>
            </div>
            <button
                type="button"
                class="icon-button reports-panel-export-button"
                data-table-export-button
                data-export-table="#reports-base-group-summary-table"
                data-export-filename="resumo_base_grupo"
                data-ui-tooltip="Baixar resumo por base grupo"
                aria-label="Baixar resumo por base grupo"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3.75v9.5m0 0 3.75-3.75M12 13.25 8.25 9.5M5.75 18.25h12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>

        <div class="reports-table-wrap">
            <table id="reports-base-group-summary-table" class="reports-summary-table">
                <thead>
                    <tr>
                        <th>Base grupo</th>
                        <th>FTTH B2C</th>
                        <th>FTTH B2B</th>
                        <th>Pontua&ccedil;&atilde;o</th>
                        <th>Receita</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reportsByBaseGroup === []): ?>
                        <tr>
                            <td colspan="5" class="table-empty-cell">Nenhum dado encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportsByBaseGroup as $row): ?>
                            <tr>
                                <td><?= e($row['base_group_name'] ?? '-') ?></td>
                                <td><?= e((string) ((int) ($row['ftth_b2c_sales'] ?? 0))) ?></td>
                                <td><?= e((string) ((int) ($row['ftth_b2b_sales'] ?? 0))) ?></td>
                                <td><?= e(format_currency_br((float) ($row['commercial_total'] ?? 0))) ?></td>
                                <td><?= e(format_currency_br((float) ($row['managerial_total'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="panel reports-panel">
    <div class="panel-head reports-panel-head">
        <div>
            <p class="eyebrow">Tabela</p>
            <h3>Resultado detalhado</h3>
        </div>
        <span class="reports-total-label">Exibindo <?= e((string) count($reportsRows)) ?> de <?= e((string) $reportsTotal) ?> registros</span>
    </div>

    <div class="reports-table-wrap reports-detail-table-wrap">
        <table class="reports-detail-table">
            <thead>
                <tr>
                        <th>COD. PAP</th>
                    <th>Data input</th>
                    <th>Status</th>
                    <th>Sub status</th>
                    <th>Opera&ccedil;&atilde;o</th>
                    <th>Base grupo</th>
                    <th>Tipo cliente</th>
                    <th>Cliente</th>
                    <th>Consultor</th>
                    <th>Auditor</th>
                    <th>Receita</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reportsRows === []): ?>
                    <tr>
                        <td colspan="11" class="table-empty-cell">Nenhum registro encontrado para os filtros selecionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportsRows as $row): ?>
                        <tr>
                            <td><?= e(($row['sale_code'] ?? '') !== '' ? substr((string) $row['sale_code'], 0, 10) : '-') ?></td>
                            <td><?= ! empty($row['sale_input_date']) ? e(date('d/m/Y', strtotime((string) $row['sale_input_date']))) : '-' ?></td>
                            <td><?= e($row['sale_status'] ?? '-') ?></td>
                            <td><?= e($row['sale_sub_status'] ?? '-') ?></td>
                            <td><?= e($row['operation_name'] ?? '-') ?></td>
                            <td><?= e($row['base_group_name'] ?? '-') ?></td>
                            <td><?= e($row['customer_type'] ?? '-') ?></td>
                            <td><?= e($row['customer_name'] ?? '-') ?></td>
                            <td><?= e($row['consultant_name'] ?? '-') ?></td>
                            <td><?= e($row['auditor_name'] ?? '-') ?></td>
                            <td><?= e(format_currency_br((float) ($row['managerial_total'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($reportsTotalPages > 1): ?>
        <nav class="queue-pagination reports-pagination" aria-label="P&aacute;ginas do relat&oacute;rio">
            <?php if ($reportsPage > 1): ?>
                <a href="<?= e(url('reports', reportsQueryParams($reportsFilters, $reportsPage - 1))) ?>">Anterior</a>
            <?php endif; ?>

            <?php for ($page = 1; $page <= $reportsTotalPages; $page += 1): ?>
                <?php if ($page === $reportsPage): ?>
                    <span class="is-current"><?= e((string) $page) ?></span>
                <?php elseif ($page === 1 || $page === $reportsTotalPages || abs($page - $reportsPage) <= 2): ?>
                    <a href="<?= e(url('reports', reportsQueryParams($reportsFilters, $page))) ?>"><?= e((string) $page) ?></a>
                <?php elseif ($page === 2 || $page === $reportsTotalPages - 1): ?>
                    <span class="is-gap">…</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($reportsPage < $reportsTotalPages): ?>
                <a href="<?= e(url('reports', reportsQueryParams($reportsFilters, $reportsPage + 1))) ?>">Pr&oacute;xima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
