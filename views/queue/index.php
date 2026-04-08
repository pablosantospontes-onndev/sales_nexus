<div data-queue-live data-queue-live-interval="4000">
    <div data-queue-live-summary>
        <?= view('queue/_summary', [
            'queueSummary' => $queueSummary ?? [],
        ]) ?>
    </div>
</div>

<section class="panel queue-filters-panel">
    <div class="panel-head">
        <p class="eyebrow">Filtros</p>
    </div>

    <?php
    $dateRangeLabel = 'Selecionar data ou intervalo';
    $statusOptions = [
        'PENDENTE INPUT' => 'PENDENTE',
        'AUDITANDO' => 'AUDITANDO',
        'FINALIZADA' => 'FINALIZADA',
    ];
    $selectedStatusLabels = array_values(array_filter(array_map(
        static fn (string $status): ?string => $statusOptions[$status] ?? null,
        $statusFilter ?? []
    )));
    $selectedModalityLabels = array_values(array_filter(array_map(
        static fn (string $modality): string => $modality,
        $modalityFilter ?? []
    )));
    $selectedOperationLabels = array_values(array_filter(array_map(
        static fn (string $operation): string => $operation,
        $operationFilter ?? []
    )));
    $customerTypeSummaryLabel = $customerTypeFilter !== ''
        ? $customerTypeFilter
        : 'Todos';
    $statusSummaryLabel = $selectedStatusLabels === []
        ? 'Todos'
        : (count($selectedStatusLabels) === 1
            ? $selectedStatusLabels[0]
            : count($selectedStatusLabels) . ' selecionados');
    $modalitySummaryLabel = $selectedModalityLabels === []
        ? 'Todas'
        : (count($selectedModalityLabels) === 1
            ? $selectedModalityLabels[0]
            : count($selectedModalityLabels) . ' selecionadas');
    $operationSummaryLabel = $selectedOperationLabels === []
        ? 'Todas'
        : (count($selectedOperationLabels) === 1
            ? $selectedOperationLabels[0]
            : count($selectedOperationLabels) . ' selecionadas');
    if (($dateFromFilter ?? null) !== null && ($dateToFilter ?? null) !== null) {
        $dateRangeLabel = $dateFromFilter === $dateToFilter
            ? format_date_br($dateFromFilter)
            : format_date_br($dateFromFilter) . ' at&eacute; ' . format_date_br($dateToFilter);
    } elseif (($dateFromFilter ?? null) !== null) {
        $dateRangeLabel = format_date_br($dateFromFilter);
    } elseif (($dateToFilter ?? null) !== null) {
        $dateRangeLabel = format_date_br($dateToFilter);
    }
    ?>

    <form method="get" class="filters queue-filters-form<?= $termFilter !== '' ? ' is-search-open' : '' ?>">
        <input type="hidden" name="route" value="queue">

        <div class="queue-status-filter" data-queue-status-filter>
            <span>Status</span>
            <button type="button" class="queue-status-trigger" data-queue-status-trigger aria-expanded="false">
                <span data-queue-status-summary><?= e($statusSummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-queue-status-dropdown hidden>
                <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                    <label class="queue-status-option">
                        <input type="checkbox" name="status[]" value="<?= e($statusValue) ?>" <?= in_array($statusValue, $statusFilter ?? [], true) ? 'checked' : '' ?> data-queue-status-checkbox>
                        <span><?= e($statusLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="queue-status-filter queue-customer-type-filter" data-queue-customer-type-filter>
            <span>Tipo cliente</span>
            <button type="button" class="queue-status-trigger" data-queue-customer-type-trigger aria-expanded="false">
                <span data-queue-customer-type-summary><?= e($customerTypeSummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-queue-customer-type-dropdown hidden>
                <label class="queue-status-option">
                    <input type="radio" name="customer_type" value="" <?= $customerTypeFilter === '' ? 'checked' : '' ?> data-queue-customer-type-radio>
                    <span>Todos</span>
                </label>
                <label class="queue-status-option">
                    <input type="radio" name="customer_type" value="B2C" <?= $customerTypeFilter === 'B2C' ? 'checked' : '' ?> data-queue-customer-type-radio>
                    <span>B2C</span>
                </label>
                <label class="queue-status-option">
                    <input type="radio" name="customer_type" value="B2B" <?= $customerTypeFilter === 'B2B' ? 'checked' : '' ?> data-queue-customer-type-radio>
                    <span>B2B</span>
                </label>
            </div>
        </div>

        <div class="queue-status-filter queue-modality-filter" data-queue-modality-filter>
            <span>Modalidade</span>
            <button type="button" class="queue-status-trigger" data-queue-modality-trigger aria-expanded="false">
                <span data-queue-modality-summary><?= e($modalitySummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-queue-modality-dropdown hidden>
                <?php foreach (($queueModalities ?? []) as $queueModality): ?>
                    <label class="queue-status-option">
                        <input type="checkbox" name="modality[]" value="<?= e($queueModality) ?>" <?= in_array($queueModality, $modalityFilter ?? [], true) ? 'checked' : '' ?> data-queue-modality-checkbox>
                        <span><?= e($queueModality) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="queue-status-filter queue-operation-filter" data-queue-operation-filter>
            <span>Opera&ccedil;&atilde;o</span>
            <button type="button" class="queue-status-trigger" data-queue-operation-trigger aria-expanded="false">
                <span data-queue-operation-summary><?= e($operationSummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-queue-operation-dropdown hidden>
                <?php foreach (($queueOperations ?? []) as $queueOperation): ?>
                    <label class="queue-status-option">
                        <input type="checkbox" name="operation[]" value="<?= e($queueOperation) ?>" <?= in_array($queueOperation, $operationFilter ?? [], true) ? 'checked' : '' ?> data-queue-operation-checkbox>
                        <span><?= e($queueOperation) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div
            class="date-range-field"
            data-date-range
            data-initial-start="<?= e($dateFromFilter ?? '') ?>"
            data-initial-end="<?= e($dateToFilter ?? '') ?>"
        >
            <span>Entrada</span>
            <input type="hidden" name="date_from" value="<?= e($dateFromFilter ?? '') ?>" data-date-range-start>
            <input type="hidden" name="date_to" value="<?= e($dateToFilter ?? '') ?>" data-date-range-end>

            <button type="button" class="date-range-trigger" data-date-range-trigger aria-expanded="false">
                <span data-date-range-label><?= e($dateRangeLabel) ?></span>
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
                    <small class="muted" data-date-range-summary><?= e($dateRangeLabel) ?></small>
                    <div class="form-actions-right">
                        <button type="button" class="ghost-button small-button" data-date-range-clear>Limpar data</button>
                        <button type="button" class="secondary-button small-button" data-date-range-close>Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="queue-search-field<?= $termFilter !== '' ? ' is-open' : '' ?>" data-queue-search-field>
            <span>Busca</span>
            <div class="queue-search-row">
                <button type="button" class="icon-button queue-search-toggle" data-queue-search-toggle data-ui-tooltip="Buscar venda" aria-label="Buscar venda" aria-expanded="<?= $termFilter !== '' ? 'true' : 'false' ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="5.75" fill="none" stroke="currentColor" stroke-width="2.15"></circle>
                        <path d="M15.35 15.35 20 20" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round"></path>
                    </svg>
                </button>
                <input type="text" name="term" value="<?= e($termFilter) ?>" placeholder="C&oacute;digo da venda ou cliente" data-queue-search-input <?= $termFilter !== '' ? '' : 'hidden' ?>>
            </div>
        </div>

        <div class="form-actions-right">
            <button type="submit" class="secondary-button">Filtrar</button>
            <a href="<?= e(url('queue')) ?>" class="icon-button" data-ui-tooltip="Limpar filtros" aria-label="Limpar filtros">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
                </svg>
            </a>
        </div>
    </form>

    <p class="muted queue-filter-note">
        Ao pesquisar por c&oacute;digo/cliente ou selecionar uma ou mais opera&ccedil;&otilde;es, a fila ignora temporariamente a vis&atilde;o regional/personalizada do usu&aacute;rio para permitir consultas pontuais em outras opera&ccedil;&otilde;es.
    </p>
</section>

<div data-queue-live-list>
    <?= view('queue/_list', [
        'authUser' => $authUser,
        'items' => $items,
        'totalItems' => $totalItems,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'statusFilter' => $statusFilter,
        'customerTypeFilter' => $customerTypeFilter,
        'modalityFilter' => $modalityFilter,
        'operationFilter' => $operationFilter,
        'termFilter' => $termFilter,
        'dateFromFilter' => $dateFromFilter,
        'dateToFilter' => $dateToFilter,
    ]) ?>
</div>

<?php if (! empty($showPrioritizeModal) && ($pendingPriorCount ?? 0) > 0): ?>
    <?php
    $prioritizeUrl = url('queue', [
        'status' => 'PENDENTE INPUT',
        'date_from' => $prioritizeDateFrom ?? '',
        'date_to' => $prioritizeDateTo ?? '',
    ]);
    $todayLabel = format_date_br(date('Y-m-d'));
    $prioritizeDateLabel = format_date_br($prioritizeDateTo ?? date('Y-m-d'));
    ?>
    <div
        class="modal-shell"
        data-queue-prioritize-modal
        data-queue-prioritize-dismiss-url="<?= e(url('queue/dismiss-prioritize')) ?>"
        data-queue-prioritize-token="<?= e(\App\Core\Csrf::token()) ?>"
    >
        <div class="modal-backdrop"></div>
        <div class="modal-card queue-prioritize-modal-card" role="dialog" aria-modal="true" aria-labelledby="queue-prioritize-title">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Fila de auditoria</p>
                    <h4 id="queue-prioritize-title">Vendas pendentes de dias anteriores</h4>
                </div>
            </div>

            <p class="muted">
                Existem <strong class="queue-prioritize-highlight"><?= e((string) ($pendingPriorCount ?? 0)) ?></strong> venda(s) de datas anteriores que ainda n&atilde;o foram finalizadas.
            </p>

            <div class="queue-prioritize-grid">
                <div class="queue-prioritize-card">
                    <span class="eyebrow">Data atual</span>
                    <strong><?= e($todayLabel) ?></strong>
                </div>
                <div class="queue-prioritize-arrow" aria-hidden="true">&#8594;</div>
                <div class="queue-prioritize-card is-emphasis">
                    <span class="eyebrow">Priorizar at&eacute;</span>
                    <strong><?= e($prioritizeDateLabel) ?></strong>
                </div>
            </div>

            <div class="queue-prioritize-note">
                <strong>O que acontece?</strong>
                <p>Ao priorizar, a fila ser&aacute; filtrada automaticamente para mostrar apenas vendas pendentes de dias anteriores.</p>
            </div>

                <div class="form-actions">
                    <div class="form-actions-left">
                    <button type="button" class="secondary-button queue-prioritize-dismiss-button" data-close-queue-prioritize-modal>
                        <i class="bi bi-hand-thumbs-down-fill" aria-hidden="true"></i>
                        Seguir sem priorizar
                    </button>
                    </div>
                    <div class="form-actions-right">
                        <button
                            type="button"
                            class="primary-button queue-prioritize-action-button"
                            data-queue-prioritize-action
                            data-queue-prioritize-url="<?= e($prioritizeUrl) ?>"
                        >
                            <i class="bi bi-hand-thumbs-up-fill" aria-hidden="true"></i>
                        Priorizar essas vendas
                        </button>
                    </div>
                </div>
        </div>
    </div>
<?php endif; ?>
