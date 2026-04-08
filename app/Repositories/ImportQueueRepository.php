<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

final class ImportQueueRepository
{
    public function dashboardStats(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $operationName = null,
        ?array $visibilityScope = null
    ): array
    {
        $connection = Database::connection();
        [$filtersSql, $bindings] = $this->buildDashboardFilters($customerType, $dateFrom, $dateTo, $operationName, $visibilityScope);

        $statement = $connection->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN sales_import_queue.audit_status = 'PENDENTE INPUT' THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN sales_import_queue.audit_status = 'FINALIZADA' THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE
                    WHEN sales_import_queue.claimed_by_user_id IS NOT NULL
                     AND sales_import_queue.audit_status <> 'FINALIZADA'
                    THEN 1
                    ELSE 0
                END), 0) AS claimed
             FROM sales_import_queue
             LEFT JOIN seller_hierarchies
                    ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                   AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, '%Y%m')
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             WHERE 1 = 1" . $filtersSql
        );
        $statement->execute($bindings);
        $stats = $statement->fetch() ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'completed' => (int) ($stats['completed'] ?? 0),
            'claimed' => (int) ($stats['claimed'] ?? 0),
        ];
    }

    public function dashboardOperations(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT hierarchy_bases.name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE hierarchy_bases.name IS NOT NULL
                  AND hierarchy_bases.name <> ""';
        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $sql .= ' ORDER BY hierarchy_bases.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueModalities(): array
    {
        $statement = Database::connection()->query(
            "SELECT DISTINCT sale_customer_type
             FROM sales_import_queue
             WHERE sale_customer_type IS NOT NULL
               AND TRIM(sale_customer_type) <> ''
             ORDER BY sale_customer_type ASC"
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['sale_customer_type'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function search(
        ?array $statuses = null,
        ?string $term = null,
        ?string $customerType = null,
        ?array $modalities = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        int $page = 1,
        int $perPage = 50,
        ?array $visibilityScope = null
    ): array
    {
        $connection = Database::connection();
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        [$filtersSql, $bindings] = $this->buildSearchFilters($statuses, $term, $customerType, $modalities, $dateFrom, $dateTo, $operationNames, $visibilityScope);

        $countStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM sales_import_queue
             LEFT JOIN seller_hierarchies
                    ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                   AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             WHERE 1 = 1' . $filtersSql
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT sales_import_queue.*, users.name AS claimed_by_name, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name, seller_hierarchies.seller_name AS consultant_name
                FROM sales_import_queue
                LEFT JOIN seller_hierarchies
                       ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                      AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                LEFT JOIN users ON users.id = sales_import_queue.claimed_by_user_id
                WHERE 1 = 1' . $filtersSql . '
                ORDER BY sales_import_queue.id DESC
                LIMIT :limit OFFSET :offset';

        $statement = $connection->prepare($sql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    public function summary(
        ?array $statuses = null,
        ?string $term = null,
        ?string $customerType = null,
        ?array $modalities = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $visibilityScope = null
    ): array {
        [$filtersSql, $bindings] = $this->buildSearchFilters(
            $statuses,
            $term,
            $customerType,
            $modalities,
            $dateFrom,
            $dateTo,
            $operationNames,
            $visibilityScope
        );

        $statement = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN sales_import_queue.audit_status = 'PENDENTE INPUT' THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN sales_import_queue.audit_status = 'AUDITANDO' THEN 1 ELSE 0 END), 0) AS auditing,
                COALESCE(SUM(CASE WHEN sales_import_queue.audit_status = 'FINALIZADA' THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(sales_import_queue.service_type, ''))) = 'VIVO TOTAL' THEN 1 ELSE 0 END), 0) AS vivo_total,
                COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(sales_import_queue.sale_customer_type, ''))) = 'UPGRADE' THEN 1 ELSE 0 END), 0) AS upgrade_total
             FROM sales_import_queue
             LEFT JOIN seller_hierarchies
                    ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                   AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, '%Y%m')
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             WHERE 1 = 1" . $filtersSql
        );
        $statement->execute($bindings);
        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'auditing' => (int) ($row['auditing'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'vivo_total' => (int) ($row['vivo_total'] ?? 0),
            'upgrade_total' => (int) ($row['upgrade_total'] ?? 0),
        ];
    }

    public function pendingBeforeDate(string $date, ?array $visibilityScope = null): int
    {
        $sql = "SELECT COUNT(*)
                FROM sales_import_queue
                LEFT JOIN seller_hierarchies
                       ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                      AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, '%Y%m')
                LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE sales_import_queue.audit_status = 'PENDENTE INPUT'
                  AND sales_import_queue.sale_input_date IS NOT NULL
                  AND sales_import_queue.sale_input_date < :date";

        [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['date' => $date] + $visibilityBindings);

        return (int) $statement->fetchColumn();
    }

    public function oldestPendingBeforeDate(string $date, ?array $visibilityScope = null): ?string
    {
        $sql = "SELECT MIN(sales_import_queue.sale_input_date)
                FROM sales_import_queue
                LEFT JOIN seller_hierarchies
                       ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                      AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, '%Y%m')
                LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE sales_import_queue.audit_status = 'PENDENTE INPUT'
                  AND sales_import_queue.sale_input_date IS NOT NULL
                  AND sales_import_queue.sale_input_date < :date";

        [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['date' => $date] + $visibilityBindings);

        $value = $statement->fetchColumn();

        return $value !== false && $value !== null ? (string) $value : null;
    }

    private function buildSearchFilters(
        ?array $statuses = null,
        ?string $term = null,
        ?string $customerType = null,
        ?array $modalities = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $visibilityScope = null
    ): array
    {
        $filtersSql = '';
        $bindings = [];

        $validOperations = array_values(array_filter(array_unique(array_map(
            static fn (mixed $operation): string => normalize_text((string) $operation),
            $operationNames ?? []
        )), static fn (string $operation): bool => $operation !== ''));
        $hasOperationFilter = $validOperations !== [];
        $hasTermFilter = $term !== null && trim($term) !== '';

        if (! $hasOperationFilter && ! $hasTermFilter) {
            [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
            $filtersSql .= $visibilitySql;
            $bindings += $visibilityBindings;
        }

        $validStatuses = array_values(array_filter(array_unique(array_map(
            static fn (mixed $status): string => strtoupper(normalize_text((string) $status)),
            $statuses ?? []
        )), static fn (string $status): bool => in_array($status, ['PENDENTE INPUT', 'AUDITANDO', 'FINALIZADA'], true)));

        if ($validStatuses !== []) {
            $statusPlaceholders = [];

            foreach ($validStatuses as $index => $status) {
                $key = 'status_' . $index;
                $statusPlaceholders[] = ':' . $key;
                $bindings[$key] = $status;
            }

            $filtersSql .= ' AND sales_import_queue.audit_status IN (' . implode(', ', $statusPlaceholders) . ')';
        }

        if ($customerType !== null && $customerType !== '') {
            $filtersSql .= ' AND sales_import_queue.customer_document_type = :customer_type';
            $bindings['customer_type'] = $customerType;
        }

        $validModalities = array_values(array_filter(array_unique(array_map(
            static fn (mixed $modality): string => normalize_text((string) $modality),
            $modalities ?? []
        )), static fn (string $modality): bool => $modality !== ''));

        if ($validModalities !== []) {
            $modalityPlaceholders = [];

            foreach ($validModalities as $index => $modality) {
                $key = 'modality_' . $index;
                $modalityPlaceholders[] = ':' . $key;
                $bindings[$key] = $modality;
            }

            $filtersSql .= ' AND sales_import_queue.sale_customer_type IN (' . implode(', ', $modalityPlaceholders) . ')';
        }

        if ($dateFrom !== null && $dateFrom !== '' && $dateTo !== null && $dateTo !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date BETWEEN :date_from AND :date_to';
            $bindings['date_from'] = $dateFrom;
            $bindings['date_to'] = $dateTo;
        } elseif ($dateFrom !== null && $dateFrom !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date = :date_from';
            $bindings['date_from'] = $dateFrom;
        } elseif ($dateTo !== null && $dateTo !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date = :date_to';
            $bindings['date_to'] = $dateTo;
        }

        if ($hasTermFilter) {
            $filtersSql .= ' AND (
                sales_import_queue.sale_code LIKE :term OR
                sales_import_queue.customer_name LIKE :term
            )';
            $bindings['term'] = '%' . trim($term) . '%';
        }

        if ($validOperations !== []) {
            $operationPlaceholders = [];

            foreach ($validOperations as $index => $operationName) {
                $key = 'operation_name_' . $index;
                $operationPlaceholders[] = ':' . $key;
                $bindings[$key] = $operationName;
            }

            $filtersSql .= ' AND hierarchy_bases.name IN (' . implode(', ', $operationPlaceholders) . ')';
        }

        return [$filtersSql, $bindings];
    }

    private function buildDashboardFilters(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $operationName = null,
        ?array $visibilityScope = null
    ): array
    {
        $filtersSql = '';
        $bindings = [];

        [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $filtersSql .= $visibilitySql;
        $bindings += $visibilityBindings;

        if ($customerType !== null && $customerType !== '') {
            $filtersSql .= ' AND sales_import_queue.customer_document_type = :customer_type';
            $bindings['customer_type'] = $customerType;
        }

        if ($dateFrom !== null && $dateFrom !== '' && $dateTo !== null && $dateTo !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date BETWEEN :date_from AND :date_to';
            $bindings['date_from'] = $dateFrom;
            $bindings['date_to'] = $dateTo;
        } elseif ($dateFrom !== null && $dateFrom !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date = :date_from';
            $bindings['date_from'] = $dateFrom;
        } elseif ($dateTo !== null && $dateTo !== '') {
            $filtersSql .= ' AND sales_import_queue.sale_input_date = :date_to';
            $bindings['date_to'] = $dateTo;
        }

        if ($operationName !== null && $operationName !== '') {
            $filtersSql .= ' AND hierarchy_bases.name = :operation_name';
            $bindings['operation_name'] = $operationName;
        }

        return [$filtersSql, $bindings];
    }

    public function findById(int $id, ?array $visibilityScope = null, ?array $operationNames = null, bool $ignoreVisibility = false): ?array
    {
        $sql = 'SELECT sales_import_queue.*, users.name AS claimed_by_name, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name, seller_hierarchies.seller_name AS consultant_name
             FROM sales_import_queue
             LEFT JOIN seller_hierarchies
                    ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                   AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
             LEFT JOIN users ON users.id = sales_import_queue.claimed_by_user_id
             WHERE sales_import_queue.id = :id';
        $bindings = ['id' => $id];

        $validOperations = array_values(array_filter(array_unique(array_map(
            static fn (mixed $operation): string => normalize_text((string) $operation),
            $operationNames ?? []
        )), static fn (string $operation): bool => $operation !== ''));

        if ($validOperations !== []) {
            $operationPlaceholders = [];

            foreach ($validOperations as $index => $operationName) {
                $key = 'operation_name_' . $index;
                $operationPlaceholders[] = ':' . $key;
                $bindings[$key] = $operationName;
            }

            $sql .= ' AND hierarchy_bases.name IN (' . implode(', ', $operationPlaceholders) . ')';
        } elseif (! $ignoreVisibility) {
            [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
            $sql .= $visibilitySql;
            $bindings += $visibilityBindings;
        }

        $sql .= ' LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetch() ?: null;
    }

    private function buildVisibilityScopeFilters(?array $visibilityScope = null): array
    {
        if (! is_array($visibilityScope)) {
            return ['', []];
        }

        $mode = strtoupper(normalize_text($visibilityScope['mode'] ?? ''));

        if ($mode === 'REGIONAL') {
            $regional = strtoupper(normalize_text($visibilityScope['regional'] ?? ''));

            if (in_array($regional, ['I', 'II'], true)) {
                return [' AND hierarchy_bases.REGIONAL = :visibility_regional', [
                    'visibility_regional' => $regional,
                ]];
            }

            return ['', []];
        }

        if ($mode === 'PERSONALIZADO') {
            $baseGroupIds = array_values(array_unique(array_filter(array_map(
                static fn (mixed $baseGroupId): int => (int) $baseGroupId,
                $visibilityScope['base_group_ids'] ?? []
            ), static fn (int $baseGroupId): bool => $baseGroupId > 0)));

            if ($baseGroupIds === []) {
                return [' AND 1 = 0', []];
            }

            $placeholders = [];
            $bindings = [];

            foreach ($baseGroupIds as $index => $baseGroupId) {
                $key = 'visibility_base_group_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $baseGroupId;
            }

            return [' AND seller_hierarchies.base_group_id IN (' . implode(', ', $placeholders) . ')', $bindings];
        }

        return ['', []];
    }

    public function claim(int $id, int $userId, bool $allowOverride = false): array
    {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare('SELECT * FROM sales_import_queue WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $sale = $statement->fetch();

            if ($sale === false) {
                throw new RuntimeException('Venda não encontrada.');
            }

            if ($sale['audit_status'] === 'FINALIZADA') {
                $connection->rollBack();
                return ['success' => false, 'message' => 'Essa venda já foi finalizada.'];
            }

            $claimedByAnotherUser = $sale['claimed_by_user_id'] !== null && (int) $sale['claimed_by_user_id'] !== $userId;

            if ($claimedByAnotherUser && ! $allowOverride) {
                $connection->rollBack();
                return ['success' => false, 'message' => 'Essa venda já foi pega por outro usuário.'];
            }

            if ($sale['claimed_by_user_id'] === null || $claimedByAnotherUser) {
                $update = $connection->prepare(
                    'UPDATE sales_import_queue
                     SET claimed_by_user_id = :user_id,
                         claimed_at = NOW(),
                         audit_status = :audit_status
                     WHERE id = :id'
                );
                $update->execute([
                    'id' => $id,
                    'user_id' => $userId,
                    'audit_status' => 'AUDITANDO',
                ]);
            }

            $connection->commit();

            if ($claimedByAnotherUser && $allowOverride) {
                return ['success' => true, 'message' => 'Venda assumida pela supervisão.'];
            }

            return ['success' => true, 'message' => 'Venda reservada para você.'];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return ['success' => false, 'message' => $throwable->getMessage()];
        }
    }

    public function abandon(int $id, int $userId, bool $allowOverride = false): array
    {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare('SELECT * FROM sales_import_queue WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $sale = $statement->fetch();

            if ($sale === false) {
                throw new RuntimeException('Venda não encontrada.');
            }

            if ($sale['audit_status'] === 'FINALIZADA') {
                $connection->rollBack();
                return ['success' => false, 'message' => 'Essa venda já foi finalizada e não pode ser abandonada.'];
            }

            if ($sale['claimed_by_user_id'] === null) {
                $connection->rollBack();
                return ['success' => false, 'message' => 'Essa venda não está reservada no momento.'];
            }

            if ((int) $sale['claimed_by_user_id'] !== $userId && ! $allowOverride) {
                $connection->rollBack();
                return ['success' => false, 'message' => 'Você só pode abandonar vendas reservadas por você.'];
            }

            $update = $connection->prepare(
                'UPDATE sales_import_queue
                 SET claimed_by_user_id = NULL,
                     claimed_at = NULL,
                     audit_status = :audit_status
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $id,
                'audit_status' => 'PENDENTE INPUT',
            ]);

            $connection->commit();

            return ['success' => true, 'message' => 'Venda abandonada e devolvida para a fila.'];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return ['success' => false, 'message' => $throwable->getMessage()];
        }
    }
}
