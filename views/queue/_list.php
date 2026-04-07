<?php
$isSupervisor = ($authUser['role'] ?? '') === 'BACKOFFICE SUPERVISOR';
$currentUserId = (int) ($authUser['id'] ?? 0);
$queueContextParams = [
    'status' => ($statusFilter ?? []) !== [] ? $statusFilter : null,
    'customer_type' => $customerTypeFilter !== '' ? $customerTypeFilter : null,
    'modality' => ($modalityFilter ?? []) !== [] ? $modalityFilter : null,
    'operation' => ($operationFilter ?? []) !== [] ? $operationFilter : null,
    'term' => $termFilter !== '' ? $termFilter : null,
    'date_from' => $dateFromFilter,
    'date_to' => $dateToFilter,
];
?>
<section class="panel">
    <div class="section-header">
        <div>
            <p class="eyebrow">Fila de Auditoria</p>
        </div>
        <span class="muted">Exibindo <?= e((string) count($items)) ?> de <?= e((string) $totalItems) ?> registros</span>
    </div>

    <div class="table-wrap">
        <table class="queue-table">
            <thead>
            <tr>
                <th>C&oacute;digo&nbsp;PAP</th>
                <th>Cliente</th>
                <th>Opera&ccedil;&atilde;o</th>
                <th>Consultor</th>
                <th class="queue-cell-center">Tipo</th>
                <th class="queue-cell-center">Modalidade</th>
                <th class="queue-cell-center">Plano</th>
                <th class="queue-cell-center">Status</th>
                <th>Backoffice</th>
                <th>Entrada</th>
                <th>A&ccedil;&otilde;es</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr>
                    <td colspan="11" class="empty-state">Nenhuma venda encontrada para esse filtro.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php $statusLabel = $item['audit_status'] === 'PENDENTE INPUT' ? 'PENDENTE' : $item['audit_status']; ?>
                    <tr class="<?= $item['claimed_by_user_id'] !== null && $item['audit_status'] !== 'FINALIZADA' ? 'queue-row-claimed' : '' ?>">
                        <td class="queue-code-cell">
                            <button
                                type="button"
                                class="copy-code-button"
                                data-copy-text="<?= e($item['sale_code']) ?>"
                                data-ui-tooltip="Clique para copiar o c&oacute;digo completo"
                            >
                                <?= e(substr((string) $item['sale_code'], 0, 10)) ?>
                            </button>
                        </td>
                        <td><?= e($item['customer_name']) ?></td>
                        <td class="queue-operation-cell"><?= e($item['base_name'] ?: '-') ?></td>
                        <td><?= e($item['consultant_name'] ?: '-') ?></td>
                        <td class="queue-cell-center"><?= e($item['customer_document_type'] ?: '-') ?></td>
                        <td class="queue-cell-center"><?= e($item['sale_customer_type'] ?: '-') ?></td>
                        <td class="queue-cell-center"><?= e($item['plan_name']) ?></td>
                        <td class="queue-cell-center"><span class="status-pill status-<?= e(strtolower(str_replace(' ', '-', $item['audit_status']))) ?>"><?= e($statusLabel) ?></span></td>
                        <td><?= e($item['claimed_by_name'] ?? '-') ?></td>
                        <td>
                            <div class="cell-stack">
                                <span><?= e(format_date_br($item['sale_input_date'])) ?></span>
                                <small><?= e($item['sale_input_time'] ? substr($item['sale_input_time'], 0, 5) : '-') ?></small>
                            </div>
                        </td>
                        <td class="actions">
                            <?php if ($item['audit_status'] === 'FINALIZADA'): ?>
                                <a href="<?= e(url('queue/show', ['id' => $item['id']] + $queueContextParams)) ?>" class="success-button small-button queue-action-button queue-view-button">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5c5.23 0 9.27 4.11 10.67 6.03a1.5 1.5 0 0 1 0 1.94C21.27 14.89 17.23 19 12 19S2.73 14.89 1.33 12.97a1.5 1.5 0 0 1 0-1.94C2.73 9.11 6.77 5 12 5zm0 2C8.04 7 4.76 9.95 3.42 12 4.76 14.05 8.04 17 12 17s7.24-2.95 8.58-5C19.24 9.95 15.96 7 12 7zm0 2.25A2.75 2.75 0 1 1 9.25 12 2.75 2.75 0 0 1 12 9.25zm0 2A.75.75 0 1 0 12.75 12 .75.75 0 0 0 12 11.25z"></path>
                                    </svg>
                                    <span>Ver venda</span>
                                </a>
                            <?php else: ?>
                                <?php
                                $isClaimed = $item['claimed_by_user_id'] !== null;
                                $isOwnedByCurrentUser = $isClaimed && (int) $item['claimed_by_user_id'] === $currentUserId;
                                $canContinueSale = $isOwnedByCurrentUser || ($isSupervisor && $isClaimed);
                                ?>
                                <?php if (! $isClaimed || $canContinueSale): ?>
                                    <form method="post" action="<?= e(url('queue/claim', ['id' => $item['id']] + $queueContextParams)) ?>">
                                        <?= \App\Core\Csrf::input() ?>
                                        <button type="submit" class="primary-button small-button queue-action-button"><?= $isClaimed ? 'Continuar' : 'Pegar venda' ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php
        $baseParams = $queueContextParams;
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);
        ?>
        <div class="pagination">
            <span class="muted">P&aacute;gina <?= e((string) $currentPage) ?> de <?= e((string) $totalPages) ?></span>

            <div class="pagination-links">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= e(url('queue', $baseParams + ['page' => $currentPage - 1])) ?>" class="ghost-link small-button">Anterior</a>
                <?php endif; ?>

                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <a
                        href="<?= e(url('queue', $baseParams + ['page' => $page])) ?>"
                        class="<?= $page === $currentPage ? 'primary-button small-button' : 'ghost-link small-button' ?>"
                    >
                        <?= e((string) $page) ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= e(url('queue', $baseParams + ['page' => $currentPage + 1])) ?>" class="ghost-link small-button">Pr&oacute;xima</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
