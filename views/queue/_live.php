<?= view('queue/_summary', [
    'queueSummary' => $queueSummary ?? [],
]) ?>

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
