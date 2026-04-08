<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DashboardAnalyticsRepository
{
    private const ONLINE_WINDOW_MINUTES = 10;

    public function kpisOverview(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $baseGroups = null,
        ?array $coordinators = null,
        ?array $visibilityScope = null
    ): array {
        return $this->fetchKpis(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
    }

    public function executiveOverview(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $baseGroups = null,
        ?array $coordinators = null,
        ?array $visibilityScope = null
    ): array {
        $kpis = $this->fetchKpis($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);
        $hourlyPeaks = $this->fetchHourlyPeaks($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);
        $topBackoffices = $this->fetchTopBackoffices($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);
        $topOperations = $this->fetchTopOperations($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);
        $slowestBackoffices = $this->fetchSlowestBackoffices($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);
        $latestFinalizations = $this->fetchLatestFinalizations($customerType, $dateFrom, $dateTo, $operationNames, $baseGroups, $coordinators, $visibilityScope);

        $peakHour = null;
        foreach ($hourlyPeaks as $hourData) {
            if ((int) ($hourData['finalized_sales'] ?? 0) <= 0) {
                continue;
            }

            if ($peakHour === null || $hourData['finalized_sales'] > $peakHour['finalized_sales']) {
                $peakHour = $hourData;
            }
        }

        return [
            'kpis' => $kpis,
            'onlineOperationalUsers' => $this->countOnlineOperationalUsers(),
            'hourlyPeaks' => $hourlyPeaks,
            'topBackoffices' => $topBackoffices,
            'topOperations' => $topOperations,
            'slowestBackoffices' => $slowestBackoffices,
            'latestFinalizations' => $latestFinalizations,
            'peakHour' => $peakHour,
            'generatedAt' => date('Y-m-d H:i:s'),
            'refreshSeconds' => 8,
        ];
    }

    private function fetchKpis(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS finalized_sales,
                COALESCE(SUM(revenue), 0) AS revenue_total,
                COALESCE(AVG(revenue), 0) AS average_revenue,
                COALESCE(AVG(CASE
                    WHEN claimed_at IS NOT NULL
                     AND finalized_at IS NOT NULL
                     AND finalized_at >= claimed_at
                    THEN TIMESTAMPDIFF(MINUTE, claimed_at, finalized_at)
                    ELSE NULL
                END), 0) AS average_minutes
             FROM ({$subquerySql}) executive_sales"
        );
        $statement->execute($bindings);
        $row = $statement->fetch() ?: [];

        return [
            'finalized_sales' => (int) ($row['finalized_sales'] ?? 0),
            'revenue_total' => (float) ($row['revenue_total'] ?? 0),
            'average_revenue' => (float) ($row['average_revenue'] ?? 0),
            'average_minutes' => (float) ($row['average_minutes'] ?? 0),
        ];
    }

    private function fetchHourlyPeaks(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                HOUR(finalized_at) AS hour_number,
                COUNT(*) AS finalized_sales,
                COALESCE(SUM(revenue), 0) AS revenue_total
             FROM ({$subquerySql}) executive_sales
             WHERE finalized_at IS NOT NULL
             GROUP BY HOUR(finalized_at)
             ORDER BY hour_number ASC"
        );
        $statement->execute($bindings);
        $indexedRows = [];

        foreach ($statement->fetchAll() as $row) {
            $hour = isset($row['hour_number']) ? (int) $row['hour_number'] : 0;
            $indexedRows[$hour] = [
                'hour_number' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . 'h',
                'finalized_sales' => (int) ($row['finalized_sales'] ?? 0),
                'revenue_total' => (float) ($row['revenue_total'] ?? 0),
            ];
        }

        $filledRows = [];

        for ($hour = 8; $hour <= 22; $hour += 1) {
            $filledRows[] = $indexedRows[$hour] ?? [
                'hour_number' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . 'h',
                'finalized_sales' => 0,
                'revenue_total' => 0.0,
            ];
        }

        return $filledRows;
    }

    private function fetchTopBackoffices(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                auditor_name,
                COUNT(*) AS finalized_sales,
                COALESCE(SUM(revenue), 0) AS revenue_total,
                COALESCE(AVG(CASE
                    WHEN claimed_at IS NOT NULL
                     AND finalized_at IS NOT NULL
                     AND finalized_at >= claimed_at
                    THEN TIMESTAMPDIFF(MINUTE, claimed_at, finalized_at)
                    ELSE NULL
                END), 0) AS average_minutes
             FROM ({$subquerySql}) executive_sales
             GROUP BY auditor_name
             ORDER BY finalized_sales DESC, revenue_total DESC, auditor_name ASC
             LIMIT 6"
        );
        $statement->execute($bindings);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['auditor_name'] ?? '-'),
                'finalized_sales' => (int) ($row['finalized_sales'] ?? 0),
                'revenue_total' => (float) ($row['revenue_total'] ?? 0),
                'average_minutes' => (float) ($row['average_minutes'] ?? 0),
            ];
        }, $statement->fetchAll());
    }

    private function fetchTopOperations(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                operation_name,
                COUNT(*) AS finalized_sales,
                COALESCE(SUM(revenue), 0) AS revenue_total
             FROM ({$subquerySql}) executive_sales
             GROUP BY operation_name
             ORDER BY finalized_sales DESC, revenue_total DESC, operation_name ASC
             LIMIT 6"
        );
        $statement->execute($bindings);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['operation_name'] ?? '-'),
                'finalized_sales' => (int) ($row['finalized_sales'] ?? 0),
                'revenue_total' => (float) ($row['revenue_total'] ?? 0),
            ];
        }, $statement->fetchAll());
    }

    private function fetchSlowestBackoffices(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                auditor_name,
                COUNT(*) AS finalized_sales,
                COALESCE(AVG(duration_minutes), 0) AS average_minutes,
                MAX(duration_minutes) AS longest_minutes
             FROM (
                SELECT
                    auditor_name,
                    CASE
                        WHEN claimed_at IS NOT NULL
                         AND finalized_at IS NOT NULL
                         AND finalized_at >= claimed_at
                        THEN TIMESTAMPDIFF(MINUTE, claimed_at, finalized_at)
                        ELSE NULL
                    END AS duration_minutes
                FROM ({$subquerySql}) executive_sales
             ) durations
             WHERE duration_minutes IS NOT NULL
             GROUP BY auditor_name
             ORDER BY average_minutes DESC, longest_minutes DESC, auditor_name ASC
             LIMIT 6"
        );
        $statement->execute($bindings);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['auditor_name'] ?? '-'),
                'finalized_sales' => (int) ($row['finalized_sales'] ?? 0),
                'average_minutes' => (float) ($row['average_minutes'] ?? 0),
                'longest_minutes' => (int) ($row['longest_minutes'] ?? 0),
            ];
        }, $statement->fetchAll());
    }

    private function fetchLatestFinalizations(
        ?string $customerType,
        ?string $dateFrom,
        ?string $dateTo,
        ?array $operationNames,
        ?array $baseGroups,
        ?array $coordinators,
        ?array $visibilityScope
    ): array {
        [$subquerySql, $bindings] = $this->finalizedSalesSubquery(
            $customerType,
            $dateFrom,
            $dateTo,
            $operationNames,
            $baseGroups,
            $coordinators,
            $visibilityScope
        );
        $statement = Database::connection()->prepare(
            "SELECT
                sale_code,
                pap_code,
                customer_name,
                auditor_name,
                operation_name,
                base_group_name,
                consultant_name,
                revenue,
                finalized_at,
                CASE
                    WHEN claimed_at IS NOT NULL
                     AND finalized_at IS NOT NULL
                     AND finalized_at >= claimed_at
                    THEN TIMESTAMPDIFF(MINUTE, claimed_at, finalized_at)
                    ELSE NULL
                END AS duration_minutes
             FROM ({$subquerySql}) executive_sales
             WHERE finalized_at IS NOT NULL
             ORDER BY finalized_at DESC
             LIMIT 6"
        );
        $statement->execute($bindings);

        return array_map(static function (array $row): array {
            return [
                'sale_code' => (string) ($row['sale_code'] ?? '-'),
                'pap_code' => (string) ($row['pap_code'] ?? '-'),
                'customer_name' => (string) ($row['customer_name'] ?? '-'),
                'auditor_name' => (string) ($row['auditor_name'] ?? '-'),
                'operation_name' => (string) ($row['operation_name'] ?? '-'),
                'base_group_name' => (string) ($row['base_group_name'] ?? '-'),
                'consultant_name' => (string) ($row['consultant_name'] ?? '-'),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'finalized_at' => (string) ($row['finalized_at'] ?? ''),
                'duration_minutes' => isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
            ];
        }, $statement->fetchAll());
    }

    private function finalizedSalesSubquery(
        ?string $customerType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $operationNames = null,
        ?array $baseGroups = null,
        ?array $coordinators = null,
        ?array $visibilityScope = null
    ): array {
        $sql = "SELECT
                    s.IMPORT_QUEUE_ID,
                    MAX(s.VENDA_ID) AS sale_code,
                    MAX(q.sale_code) AS pap_code,
                    MAX(q.claimed_at) AS claimed_at,
                    MAX(q.finalized_at) AS finalized_at,
                    COALESCE(MAX(NULLIF(TRIM(s.AUDITOR_NOME), '')), MAX(NULLIF(TRIM(u.name), '')), '-') AS auditor_name,
                    COALESCE(MAX(NULLIF(TRIM(s.CONSULTOR_BASE_NOME), '')), MAX(NULLIF(TRIM(hb.name), '')), '-') AS operation_name,
                    COALESCE(MAX(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), '')), MAX(NULLIF(TRIM(hbg.name), '')), 'Sem base grupo') AS base_group_name,
                    COALESCE(MAX(NULLIF(TRIM(s.CONSULTOR_NOME), '')), MAX(NULLIF(TRIM(sh.seller_name), '')), '-') AS consultant_name,
                    COALESCE(MAX(NULLIF(TRIM(s.CLIENTE_NOME_RAZAO_SOCIAL), '')), MAX(NULLIF(TRIM(q.customer_name), '')), '-') AS customer_name,
                    SUM(COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0)) AS revenue
                FROM sales_nexus s
                INNER JOIN sales_import_queue q
                        ON q.id = s.IMPORT_QUEUE_ID
                LEFT JOIN seller_hierarchies sh
                       ON sh.seller_cpf = COALESCE(NULLIF(TRIM(s.CONSULTOR_CPF), ''), q.seller_cpf)
                      AND sh.PERIODO_HEADCOUNT = COALESCE(NULLIF(TRIM(s.VENDA_PERIODO_INPUT), ''), DATE_FORMAT(s.VENDA_DATA_INPUT, '%Y%m'))
                LEFT JOIN hierarchy_bases hb
                       ON hb.id = sh.base_id
                LEFT JOIN hierarchy_base_groups hbg
                       ON hbg.id = sh.base_group_id
                LEFT JOIN users u
                       ON u.id = COALESCE(s.USUARIO_FINALIZACAO_ID, q.claimed_by_user_id)
                WHERE q.audit_status = 'FINALIZADA'";
        $bindings = [];

        if ($customerType !== null && $customerType !== '') {
            $sql .= ' AND s.CLIENTE_TIPO_DOCUMENTO = :customer_type';
            $bindings['customer_type'] = $customerType;
        }

        if ($dateFrom !== null && $dateFrom !== '' && $dateTo !== null && $dateTo !== '') {
            $sql .= ' AND s.VENDA_DATA_INPUT BETWEEN :date_from AND :date_to';
            $bindings['date_from'] = $dateFrom;
            $bindings['date_to'] = $dateTo;
        } elseif ($dateFrom !== null && $dateFrom !== '') {
            $sql .= ' AND s.VENDA_DATA_INPUT = :date_from';
            $bindings['date_from'] = $dateFrom;
        } elseif ($dateTo !== null && $dateTo !== '') {
            $sql .= ' AND s.VENDA_DATA_INPUT = :date_to';
            $bindings['date_to'] = $dateTo;
        }

        $validOperations = array_values(array_filter(array_unique(array_map(
            static fn (mixed $operation): string => normalize_text((string) $operation),
            $operationNames ?? []
        )), static fn (string $operation): bool => $operation !== ''));

        if ($validOperations !== []) {
            $placeholders = [];

            foreach ($validOperations as $index => $operationName) {
                $key = 'operation_name_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $operationName;
            }

            $sql .= " AND COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_NOME), ''), hb.name) IN (" . implode(', ', $placeholders) . ')';
        }

        $validBaseGroups = array_values(array_filter(array_unique(array_map(
            static fn (mixed $baseGroup): string => normalize_text((string) $baseGroup),
            $baseGroups ?? []
        )), static fn (string $baseGroup): bool => $baseGroup !== ''));

        if ($validBaseGroups !== []) {
            $placeholders = [];

            foreach ($validBaseGroups as $index => $baseGroupName) {
                $key = 'base_group_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $baseGroupName;
            }

            $sql .= " AND COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), ''), hbg.name) IN (" . implode(', ', $placeholders) . ')';
        }

        $validCoordinators = array_values(array_filter(array_unique(array_map(
            static fn (mixed $coordinator): string => normalize_text((string) $coordinator),
            $coordinators ?? []
        )), static fn (string $coordinator): bool => $coordinator !== ''));

        if ($validCoordinators !== []) {
            $placeholders = [];

            foreach ($validCoordinators as $index => $coordinatorName) {
                $key = 'coordinator_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $coordinatorName;
            }

            $sql .= " AND COALESCE(NULLIF(TRIM(s.COORDENADOR_NOME), ''), sh.coordinator_name) IN (" . implode(', ', $placeholders) . ')';
        }

        [$visibilitySql, $visibilityBindings] = $this->buildVisibilityScopeFilters($visibilityScope);
        $sql .= $visibilitySql;
        $bindings += $visibilityBindings;

        $sql .= ' GROUP BY s.IMPORT_QUEUE_ID';

        return [$sql, $bindings];
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
                return [' AND hb.REGIONAL = :visibility_regional', [
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

            return [' AND sh.base_group_id IN (' . implode(', ', $placeholders) . ')', $bindings];
        }

        return ['', []];
    }

    private function countOnlineOperationalUsers(): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM users
             WHERE is_active = 1
               AND role IN ('BACKOFFICE', 'BACKOFFICE SUPERVISOR')
               AND current_session_id IS NOT NULL
               AND current_session_id <> ''
               AND last_seen_at IS NOT NULL
               AND last_seen_at >= DATE_SUB(NOW(), INTERVAL " . self::ONLINE_WINDOW_MINUTES . " MINUTE)"
        );
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
