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
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $coordinatorNames = null,
        ?array $visibilityScope = null
    ): array
    {
        $connection = Database::connection();
        [$filtersSql, $bindings] = $this->buildDashboardFilters(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroupNames,
            $coordinatorNames,
            $visibilityScope
        );

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
             LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
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

    public function finalizedMonthComparison(
        ?string $customerType = null,
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $coordinatorNames = null,
        ?array $visibilityScope = null,
        ?string $referenceDate = null
    ): array {
        $referenceDate = $referenceDate !== null && $referenceDate !== '' ? $referenceDate : date('Y-m-d');
        $reference = new \DateTimeImmutable($referenceDate);
        $currentEnd = $reference;
        $currentMonthStart = $reference->modify('first day of this month');
        $currentStart = $reference->modify('-6 days');

        if ($currentStart < $currentMonthStart) {
            $currentStart = $currentMonthStart;
        }

        $length = $currentStart->diff($currentEnd)->days + 1;
        $previousMonthStart = $reference->modify('first day of last month');
        $previousMonthEnd = $previousMonthStart->modify('+' . ((int) $reference->format('j') - 1) . ' days');
        $previousMonthLastDay = $previousMonthStart->modify('last day of this month');

        if ($previousMonthEnd > $previousMonthLastDay) {
            $previousMonthEnd = $previousMonthLastDay;
        }

        $previousStart = $previousMonthEnd->modify('-' . max(0, $length - 1) . ' days');

        [$filtersSql, $bindings] = $this->buildDashboardFilters(
            $customerType,
            null,
            null,
            $operationNames,
            $baseGroupNames,
            $coordinatorNames,
            $visibilityScope
        );

        $baseSql = "FROM sales_import_queue
            INNER JOIN sales_nexus
                    ON sales_nexus.IMPORT_QUEUE_ID = sales_import_queue.id
            LEFT JOIN seller_hierarchies
                   ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                  AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, '%Y%m')
            LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
            LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
            WHERE 1 = 1" . $filtersSql . "
              AND sales_import_queue.audit_status = 'FINALIZADA'";

        $currentBindings = [
            'current_date_from' => $currentStart->format('Y-m-d'),
            'current_date_to' => $currentEnd->format('Y-m-d'),
        ];
        $previousBindings = [
            'previous_date_from' => $previousStart->format('Y-m-d'),
            'previous_date_to' => $previousMonthEnd->format('Y-m-d'),
        ];

        $currentStatement = Database::connection()->prepare(
            "SELECT DATE(sales_nexus.VENDA_DATA_INPUT) AS ref_date, COUNT(DISTINCT sales_import_queue.id) AS total
             {$baseSql}
               AND sales_nexus.VENDA_DATA_INPUT IS NOT NULL
               AND DATE(sales_nexus.VENDA_DATA_INPUT) BETWEEN :current_date_from AND :current_date_to
             GROUP BY DATE(sales_nexus.VENDA_DATA_INPUT)"
        );
        $currentStatement->execute($bindings + $currentBindings);
        $currentRows = $currentStatement->fetchAll();

        $previousStatement = Database::connection()->prepare(
            "SELECT DATE(sales_nexus.VENDA_DATA_INPUT) AS ref_date, COUNT(DISTINCT sales_import_queue.id) AS total
             {$baseSql}
               AND sales_nexus.VENDA_DATA_INPUT IS NOT NULL
               AND DATE(sales_nexus.VENDA_DATA_INPUT) BETWEEN :previous_date_from AND :previous_date_to
             GROUP BY DATE(sales_nexus.VENDA_DATA_INPUT)"
        );
        $previousStatement->execute($bindings + $previousBindings);
        $previousRows = $previousStatement->fetchAll();

        $currentCounts = [];
        foreach ($currentRows as $row) {
            $dateKey = (string) ($row['ref_date'] ?? '');
            if ($dateKey !== '') {
                $currentCounts[$dateKey] = (int) ($row['total'] ?? 0);
            }
        }

        $previousCounts = [];
        foreach ($previousRows as $row) {
            $dateKey = (string) ($row['ref_date'] ?? '');
            if ($dateKey !== '') {
                $previousCounts[$dateKey] = (int) ($row['total'] ?? 0);
            }
        }

        $days = [];
        $currentCursor = $currentStart;
        $previousCursor = $previousStart;

        while ($currentCursor <= $currentEnd) {
            $currentIsSunday = (int) $currentCursor->format('w') === 0;
            $previousIsSunday = (int) $previousCursor->format('w') === 0;
            $currentKey = $currentCursor->format('Y-m-d');
            $previousKey = $previousCursor->format('Y-m-d');

            if (! ($currentIsSunday && $previousIsSunday)) {
                $days[] = [
                    'current_date' => $currentKey,
                    'previous_date' => $previousKey,
                    'current_count' => (int) ($currentCounts[$currentKey] ?? 0),
                    'previous_count' => (int) ($previousCounts[$previousKey] ?? 0),
                    'current_is_sunday' => $currentIsSunday,
                    'previous_is_sunday' => $previousIsSunday,
                ];
            }

            $currentCursor = $currentCursor->modify('+1 day');
            $previousCursor = $previousCursor->modify('+1 day');
        }

        return [
            'current_start' => $currentStart->format('Y-m-d'),
            'current_end' => $currentEnd->format('Y-m-d'),
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousMonthEnd->format('Y-m-d'),
            'current_month_label' => $currentEnd->format('m/Y'),
            'previous_month_label' => $previousMonthEnd->format('m/Y'),
            'days' => $days,
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

    public function dashboardBaseGroups(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT hierarchy_base_groups.name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE hierarchy_base_groups.name IS NOT NULL
                  AND hierarchy_base_groups.name <> ""';
        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $sql .= ' ORDER BY hierarchy_base_groups.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function dashboardCoordinators(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT seller_hierarchies.coordinator_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE seller_hierarchies.coordinator_name IS NOT NULL
                  AND TRIM(seller_hierarchies.coordinator_name) <> ""';
        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $sql .= ' ORDER BY seller_hierarchies.coordinator_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function dashboardSupervisors(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT seller_hierarchies.supervisor_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE seller_hierarchies.supervisor_name IS NOT NULL
                  AND TRIM(seller_hierarchies.supervisor_name) <> ""';
        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $sql .= ' ORDER BY seller_hierarchies.supervisor_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function dashboardManagers(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT seller_hierarchies.manager_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                WHERE seller_hierarchies.manager_name IS NOT NULL
                  AND TRIM(seller_hierarchies.manager_name) <> ""';
        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $sql .= ' ORDER BY seller_hierarchies.manager_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueBaseGroups(
        ?array $operationNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
        ?array $visibilityScope = null
    ): array
    {
        $sql = 'SELECT DISTINCT hierarchy_base_groups.name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE hierarchy_base_groups.name IS NOT NULL
                  AND hierarchy_base_groups.name <> ""';

        [$filtersSql, $bindings] = $this->buildQueueHierarchyFilters(
            $operationNames,
            null,
            $supervisorNames,
            $coordinatorNames,
            $managerNames,
            $visibilityScope
        );
        $sql .= $filtersSql;
        $sql .= ' ORDER BY hierarchy_base_groups.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueOperations(
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
        ?array $visibilityScope = null
    ): array {
        $sql = 'SELECT DISTINCT hierarchy_bases.name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE hierarchy_bases.name IS NOT NULL
                  AND hierarchy_bases.name <> ""';

        [$filtersSql, $bindings] = $this->buildQueueHierarchyFilters(
            null,
            $baseGroupNames,
            $supervisorNames,
            $coordinatorNames,
            $managerNames,
            $visibilityScope
        );
        $sql .= $filtersSql;
        $sql .= ' ORDER BY hierarchy_bases.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueSupervisors(
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
        ?array $visibilityScope = null
    ): array {
        $sql = 'SELECT DISTINCT seller_hierarchies.supervisor_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE seller_hierarchies.supervisor_name IS NOT NULL
                  AND TRIM(seller_hierarchies.supervisor_name) <> ""';

        [$filtersSql, $bindings] = $this->buildQueueHierarchyFilters(
            $operationNames,
            $baseGroupNames,
            null,
            $coordinatorNames,
            $managerNames,
            $visibilityScope
        );
        $sql .= $filtersSql;
        $sql .= ' ORDER BY seller_hierarchies.supervisor_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueCoordinators(
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $managerNames = null,
        ?array $visibilityScope = null
    ): array {
        $sql = 'SELECT DISTINCT seller_hierarchies.coordinator_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE seller_hierarchies.coordinator_name IS NOT NULL
                  AND TRIM(seller_hierarchies.coordinator_name) <> ""';

        [$filtersSql, $bindings] = $this->buildQueueHierarchyFilters(
            $operationNames,
            $baseGroupNames,
            $supervisorNames,
            null,
            $managerNames,
            $visibilityScope
        );
        $sql .= $filtersSql;
        $sql .= ' ORDER BY seller_hierarchies.coordinator_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueManagers(
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $visibilityScope = null
    ): array {
        $sql = 'SELECT DISTINCT seller_hierarchies.manager_name AS name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE seller_hierarchies.manager_name IS NOT NULL
                  AND TRIM(seller_hierarchies.manager_name) <> ""';

        [$filtersSql, $bindings] = $this->buildQueueHierarchyFilters($operationNames, $baseGroupNames, $supervisorNames, $coordinatorNames, null, $visibilityScope);
        $sql .= $filtersSql;
        $sql .= ' ORDER BY seller_hierarchies.manager_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function queueHierarchyMatrix(?array $visibilityScope = null): array
    {
        $sql = 'SELECT DISTINCT
                    hierarchy_bases.name AS operation_name,
                    hierarchy_base_groups.name AS base_group_name,
                    seller_hierarchies.supervisor_name AS supervisor_name,
                    seller_hierarchies.coordinator_name AS coordinator_name,
                    seller_hierarchies.manager_name AS manager_name
                FROM sales_import_queue
                INNER JOIN seller_hierarchies
                        ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                       AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE 1 = 1';

        [$visibilitySql, $bindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll() ?: [];
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
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
        int $page = 1,
        int $perPage = 50,
        ?array $visibilityScope = null
    ): array
    {
        $connection = Database::connection();
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        [$filtersSql, $bindings] = $this->buildSearchFilters(
            $statuses,
            $term,
            $customerType,
            $modalities,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroupNames,
            $supervisorNames,
            $coordinatorNames,
            $managerNames,
            $visibilityScope
        );

        $countStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM sales_import_queue
             LEFT JOIN seller_hierarchies
                    ON seller_hierarchies.seller_cpf = sales_import_queue.seller_cpf
                   AND seller_hierarchies.PERIODO_HEADCOUNT = DATE_FORMAT(sales_import_queue.sale_input_date, \'%Y%m\')
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
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
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
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
            $baseGroupNames,
            $supervisorNames,
            $coordinatorNames,
            $managerNames,
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
             LEFT JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
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
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
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

        $validBaseGroups = array_values(array_filter(array_unique(array_map(
            static fn (mixed $baseGroup): string => normalize_text((string) $baseGroup),
            $baseGroupNames ?? []
        )), static fn (string $baseGroup): bool => $baseGroup !== ''));

        if ($validBaseGroups !== []) {
            $baseGroupPlaceholders = [];

            foreach ($validBaseGroups as $index => $baseGroupName) {
                $key = 'base_group_' . $index;
                $baseGroupPlaceholders[] = ':' . $key;
                $bindings[$key] = $baseGroupName;
            }

            $filtersSql .= ' AND hierarchy_base_groups.name IN (' . implode(', ', $baseGroupPlaceholders) . ')';
        }

        $validSupervisors = array_values(array_filter(array_unique(array_map(
            static fn (mixed $supervisor): string => normalize_text((string) $supervisor),
            $supervisorNames ?? []
        )), static fn (string $supervisor): bool => $supervisor !== ''));

        if ($validSupervisors !== []) {
            $supervisorPlaceholders = [];

            foreach ($validSupervisors as $index => $supervisorName) {
                $key = 'supervisor_name_' . $index;
                $supervisorPlaceholders[] = ':' . $key;
                $bindings[$key] = $supervisorName;
            }

            $filtersSql .= ' AND seller_hierarchies.supervisor_name IN (' . implode(', ', $supervisorPlaceholders) . ')';
        }

        $validCoordinators = array_values(array_filter(array_unique(array_map(
            static fn (mixed $coordinator): string => normalize_text((string) $coordinator),
            $coordinatorNames ?? []
        )), static fn (string $coordinator): bool => $coordinator !== ''));

        if ($validCoordinators !== []) {
            $coordinatorPlaceholders = [];

            foreach ($validCoordinators as $index => $coordinatorName) {
                $key = 'coordinator_name_' . $index;
                $coordinatorPlaceholders[] = ':' . $key;
                $bindings[$key] = $coordinatorName;
            }

            $filtersSql .= ' AND seller_hierarchies.coordinator_name IN (' . implode(', ', $coordinatorPlaceholders) . ')';
        }

        $validManagers = array_values(array_filter(array_unique(array_map(
            static fn (mixed $manager): string => normalize_text((string) $manager),
            $managerNames ?? []
        )), static fn (string $manager): bool => $manager !== ''));

        if ($validManagers !== []) {
            $managerPlaceholders = [];

            foreach ($validManagers as $index => $managerName) {
                $key = 'manager_name_' . $index;
                $managerPlaceholders[] = ':' . $key;
                $bindings[$key] = $managerName;
            }

            $filtersSql .= ' AND seller_hierarchies.manager_name IN (' . implode(', ', $managerPlaceholders) . ')';
        }

        return [$filtersSql, $bindings];
    }

    private function buildQueueHierarchyFilters(
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $supervisorNames = null,
        ?array $coordinatorNames = null,
        ?array $managerNames = null,
        ?array $visibilityScope = null
    ): array {
        $filtersSql = '';
        $bindings = [];

        if ($operationNames !== null) {
            $validOperations = array_values(array_filter(array_unique(array_map(
                static fn (mixed $operation): string => normalize_text((string) $operation),
                $operationNames
            )), static fn (string $operation): bool => $operation !== ''));

            if ($validOperations !== []) {
                $placeholders = [];

                foreach ($validOperations as $index => $operationName) {
                    $key = 'queue_operation_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $operationName;
                }

                $filtersSql .= ' AND hierarchy_bases.name IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if ($baseGroupNames !== null) {
            $validBaseGroups = array_values(array_filter(array_unique(array_map(
                static fn (mixed $baseGroup): string => normalize_text((string) $baseGroup),
                $baseGroupNames
            )), static fn (string $baseGroup): bool => $baseGroup !== ''));

            if ($validBaseGroups !== []) {
                $placeholders = [];

                foreach ($validBaseGroups as $index => $baseGroupName) {
                    $key = 'queue_base_group_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $baseGroupName;
                }

                $filtersSql .= ' AND hierarchy_base_groups.name IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if ($supervisorNames !== null) {
            $validSupervisors = array_values(array_filter(array_unique(array_map(
                static fn (mixed $supervisor): string => normalize_text((string) $supervisor),
                $supervisorNames
            )), static fn (string $supervisor): bool => $supervisor !== ''));

            if ($validSupervisors !== []) {
                $placeholders = [];

                foreach ($validSupervisors as $index => $supervisorName) {
                    $key = 'queue_supervisor_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $supervisorName;
                }

                $filtersSql .= ' AND seller_hierarchies.supervisor_name IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if ($coordinatorNames !== null) {
            $validCoordinators = array_values(array_filter(array_unique(array_map(
                static fn (mixed $coordinator): string => normalize_text((string) $coordinator),
                $coordinatorNames
            )), static fn (string $coordinator): bool => $coordinator !== ''));

            if ($validCoordinators !== []) {
                $placeholders = [];

                foreach ($validCoordinators as $index => $coordinatorName) {
                    $key = 'queue_coordinator_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $coordinatorName;
                }

                $filtersSql .= ' AND seller_hierarchies.coordinator_name IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if ($managerNames !== null) {
            $validManagers = array_values(array_filter(array_unique(array_map(
                static fn (mixed $manager): string => normalize_text((string) $manager),
                $managerNames
            )), static fn (string $manager): bool => $manager !== ''));

            if ($validManagers !== []) {
                $placeholders = [];

                foreach ($validManagers as $index => $managerName) {
                    $key = 'queue_manager_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $managerName;
                }

                $filtersSql .= ' AND seller_hierarchies.manager_name IN (' . implode(', ', $placeholders) . ')';
            }
        }

        [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $filtersSql .= $visibilitySql;
        $bindings += $visibilityBindings;

        return [$filtersSql, $bindings];
    }

    private function buildDashboardFilters(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $baseGroupNames = null,
        ?array $coordinatorNames = null,
        ?array $visibilityScope = null
    ): array {
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

        $validOperations = array_values(array_filter(array_unique(array_map(
            static fn (mixed $operation): string => normalize_text((string) $operation),
            $operationNames ?? []
        )), static fn (string $operation): bool => $operation !== ''));

        if ($validOperations !== []) {
            $operationPlaceholders = [];

            foreach ($validOperations as $index => $operationName) {
                $key = 'dashboard_operation_' . $index;
                $operationPlaceholders[] = ':' . $key;
                $bindings[$key] = $operationName;
            }

            $filtersSql .= ' AND hierarchy_bases.name IN (' . implode(', ', $operationPlaceholders) . ')';
        }

        $validBaseGroups = array_values(array_filter(array_unique(array_map(
            static fn (mixed $baseGroup): string => normalize_text((string) $baseGroup),
            $baseGroupNames ?? []
        )), static fn (string $baseGroup): bool => $baseGroup !== ''));

        if ($validBaseGroups !== []) {
            $placeholders = [];

            foreach ($validBaseGroups as $index => $baseGroupName) {
                $key = 'dashboard_base_group_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $baseGroupName;
            }

            $filtersSql .= ' AND hierarchy_base_groups.name IN (' . implode(', ', $placeholders) . ')';
        }

        $validCoordinators = array_values(array_filter(array_unique(array_map(
            static fn (mixed $coordinator): string => normalize_text((string) $coordinator),
            $coordinatorNames ?? []
        )), static fn (string $coordinator): bool => $coordinator !== ''));

        if ($validCoordinators !== []) {
            $placeholders = [];

            foreach ($validCoordinators as $index => $coordinatorName) {
                $key = 'dashboard_coordinator_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $coordinatorName;
            }

            $filtersSql .= ' AND seller_hierarchies.coordinator_name IN (' . implode(', ', $placeholders) . ')';
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
