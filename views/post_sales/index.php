<?php
$postSalesFilters = $postSalesFilters ?? [];
$postSalesPeriods = $postSalesPeriods ?? [];
$postSalesRows = $postSalesRows ?? [];
$postSalesTotal = (int) ($postSalesTotal ?? 0);
$postSalesPage = (int) ($postSalesPage ?? 1);
$postSalesTotalPages = (int) ($postSalesTotalPages ?? 1);
$postSalesQueryParams = $postSalesQueryParams ?? [];
$postSalesLogs = $postSalesLogs ?? [];
$selectedPeriod = (string) ($postSalesFilters['period'] ?? '');
$termFilter = (string) ($postSalesFilters['term'] ?? '');

$formatPeriod = static function (string $period): string {
    if (preg_match('/^\d{6}$/', $period) !== 1) {
        return $period;
    }

    return substr($period, 4, 2) . '/' . substr($period, 0, 4);
};
?>

<section class="panel dashboard-filters-panel is-open" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="true"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">P&oacute;s venda</small>
            <strong>Importar arquivo XLSX</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon>&minus;</span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body>
        <form method="post" enctype="multipart/form-data" class="zip-import-form">
            <?= \App\Core\Csrf::input() ?>

            <label class="zip-upload-field" data-file-field>
                <span>Arquivo XLSX</span>
                <span class="upload-field-control" data-file-control>
                    <input type="file" name="xlsx_file" accept=".xlsx" required data-file-input data-empty-label="Nenhum arquivo escolhido">
                    <span class="upload-field-button" aria-hidden="true">Escolher arquivo</span>
                    <span class="upload-field-name" data-file-name>Nenhum arquivo escolhido</span>
                </span>
            </label>

            <button type="submit" class="secondary-button zip-import-submit">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Importar XLSX</span>
            </button>
        </form>

        <p class="muted zip-import-note">
            Subir arquivo de p&oacute;s venda para atualizar valores de status e sub status.
        </p>
    </div>
</section>

<section class="panel reports-filters-panel">
    <div class="reports-filter-head">
        <div>
            <p class="eyebrow">Filtros</p>
            <h3>Refinar p&oacute;s-venda</h3>
        </div>
        <?php if ($termFilter !== ''): ?>
            <span class="reports-total-label">A busca ignora o per&iacute;odo selecionado.</span>
        <?php endif; ?>
    </div>

    <form method="get" action="index.php" class="post-sales-filters-form" data-auto-submit-form>
        <input type="hidden" name="route" value="post-sales">

        <section class="reports-filter-grid">
            <div class="queue-status-filter reports-checklist-filter" data-reports-checklist-filter data-default-label="&Uacute;ltimo per&iacute;odo">
                <span>Per&iacute;odo input</span>
                <button type="button" class="queue-status-trigger" data-reports-checklist-trigger aria-expanded="false">
                    <span data-reports-checklist-summary>&Uacute;ltimo per&iacute;odo</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </button>
                <div class="queue-status-dropdown" data-reports-checklist-dropdown hidden>
                    <?php if ($postSalesPeriods === []): ?>
                        <div class="queue-status-option">Nenhum per&iacute;odo dispon&iacute;vel.</div>
                    <?php else: ?>
                        <?php foreach ($postSalesPeriods as $period): ?>
                            <?php $label = $formatPeriod((string) $period); ?>
                            <label class="queue-status-option">
                                <input
                                    type="radio"
                                    name="period"
                                    value="<?= e((string) $period) ?>"
                                    data-label="<?= e($label) ?>"
                                    data-reports-checklist-checkbox
                                    <?= (string) $period === $selectedPeriod ? 'checked' : '' ?>
                                >
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                        placeholder="Ordem de servi&ccedil;o ou documento do cliente"
                    >
                </div>
            </div>

            <div class="reports-filter-actions">
                <button type="submit" name="apply" value="1" class="secondary-button reports-action-button">Atualizar</button>
                <a href="<?= e(url('post-sales', ['clear' => 1])) ?>" class="icon-button" data-ui-tooltip="Limpar filtros" aria-label="Limpar filtros">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
                    </svg>
                </a>
            </div>
        </section>
    </form>
</section>

<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="false"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">P&oacute;s venda</small>
            <strong>Log de atualiza&ccedil;&otilde;es</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon>+</span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body hidden>
        <div class="reports-table-wrap">
            <table class="reports-summary-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usu&aacute;rio</th>
                        <th>Status</th>
                        <th>Mensagem</th>
                        <th>Atualizados</th>
                        <th>N&atilde;o encontrados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($postSalesLogs === []): ?>
                        <tr>
                            <td colspan="6" class="table-empty-cell">Nenhum log registrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($postSalesLogs as $log): ?>
                            <tr>
                                <td><?= e(format_datetime_br((string) ($log['created_at'] ?? ''))) ?></td>
                                <td><?= e($log['user_name'] ?? '-') ?></td>
                                <td><?= e($log['status'] ?? '-') ?></td>
                                <td><?= e($log['message'] ?? '-') ?></td>
                                <td><?= e((string) ((int) ($log['updated_rows'] ?? 0))) ?></td>
                                <td><?= e((string) ((int) ($log['not_found_rows'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel reports-panel">
    <div class="panel-head reports-panel-head">
        <div>
            <p class="eyebrow">Tabela</p>
            <h3>Resultado detalhado</h3>
        </div>
        <span class="reports-total-label">Exibindo <?= e((string) count($postSalesRows)) ?> de <?= e((string) $postSalesTotal) ?> registros</span>
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
                    <th>Ordem servi&ccedil;o</th>
                    <th>Documento</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($postSalesRows === []): ?>
                    <tr>
                        <td colspan="13" class="table-empty-cell">Nenhum registro encontrado para os filtros selecionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($postSalesRows as $row): ?>
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
                            <td><?= e($row['service_order'] ?? '-') ?></td>
                            <td><?= e($row['customer_document'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($postSalesTotalPages > 1): ?>
        <nav class="queue-pagination reports-pagination" aria-label="P&aacute;ginas do p&oacute;s-venda">
            <?php if ($postSalesPage > 1): ?>
                <a href="<?= e(url('post-sales', postSalesQueryParams($postSalesFilters, $postSalesPage - 1))) ?>">Anterior</a>
            <?php endif; ?>

            <?php for ($page = 1; $page <= $postSalesTotalPages; $page += 1): ?>
                <?php if ($page === $postSalesPage): ?>
                    <span class="is-current"><?= e((string) $page) ?></span>
                <?php elseif ($page === 1 || $page === $postSalesTotalPages || abs($page - $postSalesPage) <= 2): ?>
                    <a href="<?= e(url('post-sales', postSalesQueryParams($postSalesFilters, $page))) ?>"><?= e((string) $page) ?></a>
                <?php elseif ($page === 2 || $page === $postSalesTotalPages - 1): ?>
                    <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($postSalesPage < $postSalesTotalPages): ?>
                <a href="<?= e(url('post-sales', postSalesQueryParams($postSalesFilters, $postSalesPage + 1))) ?>">Pr&oacute;xima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
