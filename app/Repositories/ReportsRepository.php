<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ReportsRepository
{
    public function availableYears(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT YEAR(VENDA_DATA_INPUT) AS year_value
             FROM sales_nexus
             WHERE VENDA_DATA_INPUT IS NOT NULL
             ORDER BY year_value DESC'
        );

        $years = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['year_value'] ?? 0),
            $statement->fetchAll()
        ), static fn (int $year): bool => $year > 0));

        $currentYear = (int) date('Y');

        if (! in_array($currentYear, $years, true)) {
            $years[] = $currentYear;
            rsort($years);
        }

        return $years;
    }

    public function filterOptions(): array
    {
        return [
            'statuses' => $this->distinctColumnValues('POSVENDA_STATUS'),
            'sub_statuses' => $this->distinctColumnValues('POSVENDA_SUB_STATUS'),
            'operations' => $this->distinctColumnValues('CONSULTOR_BASE_NOME'),
            'base_groups' => $this->distinctColumnValues('CONSULTOR_BASE_GRUPO'),
            'customer_types' => $this->distinctColumnValues('CLIENTE_TIPO_DOCUMENTO'),
            'supervisors' => $this->distinctColumnValues('SUPERVISOR_NOME'),
            'coordinators' => $this->distinctColumnValues('COORDENADOR_NOME'),
            'managers' => $this->distinctColumnValues('GERENTE_BASE_NOME'),
            'consultants' => $this->distinctColumnValues('CONSULTOR_NOME'),
            'territories' => $this->distinctColumnValues('CNLAT_TERRITORIO'),
        ];
    }

    public function filterOptionsForFilters(array $filters): array
    {
        return [
            'statuses' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('POSVENDA_STATUS', $filters, ['statuses']),
                (array) ($filters['statuses'] ?? [])
            ),
            'sub_statuses' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('POSVENDA_SUB_STATUS', $filters, ['sub_statuses']),
                (array) ($filters['sub_statuses'] ?? [])
            ),
            'operations' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('CONSULTOR_BASE_NOME', $filters, ['operations']),
                (array) ($filters['operations'] ?? [])
            ),
            'base_groups' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('CONSULTOR_BASE_GRUPO', $filters, ['base_groups']),
                (array) ($filters['base_groups'] ?? [])
            ),
            'customer_types' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('CLIENTE_TIPO_DOCUMENTO', $filters, ['customer_types']),
                (array) ($filters['customer_types'] ?? [])
            ),
            'supervisors' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('SUPERVISOR_NOME', $filters, ['supervisors']),
                (array) ($filters['supervisors'] ?? [])
            ),
            'coordinators' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('COORDENADOR_NOME', $filters, ['coordinators']),
                (array) ($filters['coordinators'] ?? [])
            ),
            'managers' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('GERENTE_BASE_NOME', $filters, ['managers']),
                (array) ($filters['managers'] ?? [])
            ),
            'consultants' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('CONSULTOR_NOME', $filters, ['consultants']),
                (array) ($filters['consultants'] ?? [])
            ),
            'territories' => $this->mergeSelectedOptions(
                $this->distinctColumnValuesFiltered('CNLAT_TERRITORIO', $filters, ['territories']),
                (array) ($filters['territories'] ?? [])
            ),
        ];
    }

    public function overview(array $filters): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');

        $statement = Database::connection()->prepare(
            "SELECT
                COUNT(DISTINCT s.IMPORT_QUEUE_ID) AS total_sales,
                COUNT(DISTINCT NULLIF(TRIM(COALESCE(s.CONSULTOR_BASE_NOME, '')), '')) AS operations_count,
                COUNT(DISTINCT NULLIF(TRIM(COALESCE(s.CONSULTOR_BASE_GRUPO, '')), '')) AS base_groups_count,
                SUM(COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0)) AS managerial_total,
                SUM(COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0)) AS commercial_total,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(s.POSVENDA_STATUS, ''))) IN ('ATIVO', 'MOVEL') THEN COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0) ELSE 0 END) AS managerial_active_total,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(s.POSVENDA_STATUS, ''))) IN ('ATIVO', 'MOVEL') THEN COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0) ELSE 0 END) AS commercial_active_total,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(s.POSVENDA_STATUS, ''))) LIKE 'CANCEL%' THEN COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0) ELSE 0 END) AS managerial_canceled_total,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(s.POSVENDA_STATUS, ''))) LIKE 'CANCEL%' THEN COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0) ELSE 0 END) AS commercial_canceled_total,
                COUNT(DISTINCT CASE WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2C' AND TRIM(COALESCE(s.PRODUTO_TIPO, '')) = 'DADOS' THEN s.IMPORT_QUEUE_ID END) AS ftth_b2c_sales,
                COUNT(DISTINCT CASE WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2B' AND TRIM(COALESCE(s.PRODUTO_TIPO, '')) = 'DADOS' THEN s.IMPORT_QUEUE_ID END) AS ftth_b2b_sales,
                COUNT(DISTINCT CASE WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2B' THEN s.IMPORT_QUEUE_ID END) AS b2b_sales,
                COUNT(DISTINCT CASE WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2C' THEN s.IMPORT_QUEUE_ID END) AS b2c_sales
             FROM sales_nexus s
             WHERE 1 = 1{$where}"
        );
        $statement->execute($bindings);
        $row = $statement->fetch() ?: [];

        $totalSales = (int) ($row['total_sales'] ?? 0);
        $managerialTotal = (float) ($row['managerial_total'] ?? 0);
        $ftthB2cSales = (int) ($row['ftth_b2c_sales'] ?? 0);
        $ftthB2bSales = (int) ($row['ftth_b2b_sales'] ?? 0);

        return [
            'total_sales' => $totalSales,
            'operations_count' => (int) ($row['operations_count'] ?? 0),
            'base_groups_count' => (int) ($row['base_groups_count'] ?? 0),
            'managerial_total' => $managerialTotal,
            'commercial_total' => (float) ($row['commercial_total'] ?? 0),
            'managerial_active_total' => (float) ($row['managerial_active_total'] ?? 0),
            'commercial_active_total' => (float) ($row['commercial_active_total'] ?? 0),
            'managerial_canceled_total' => (float) ($row['managerial_canceled_total'] ?? 0),
            'commercial_canceled_total' => (float) ($row['commercial_canceled_total'] ?? 0),
            'ticket_average' => $totalSales > 0 ? $managerialTotal / $totalSales : 0.0,
            'ftth_b2c_sales' => $ftthB2cSales,
            'ftth_b2b_sales' => $ftthB2bSales,
            'ftth_total_sales' => $ftthB2cSales + $ftthB2bSales,
            'b2b_sales' => (int) ($row['b2b_sales'] ?? 0),
            'b2c_sales' => (int) ($row['b2c_sales'] ?? 0),
        ];
    }

    public function summaryByOperation(array $filters, int $limit = 12): array
    {
        return $this->summaryByGroupedSales(
            $filters,
            "COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_NOME), ''), 'Sem operação')",
            'operation_name',
            $limit
        );
    }

    public function summaryByBaseGroup(array $filters, int $limit = 12): array
    {
        return $this->summaryByGroupedSales(
            $filters,
            "COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), ''), 'Sem base grupo')",
            'base_group_name',
            $limit
        );

        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');
        $limit = max(1, $limit);

        $statement = Database::connection()->prepare(
            "SELECT
                COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), ''), 'Sem base grupo') AS base_group_name,
                COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_NOME), ''), 'Sem operação') AS operation_name,
                COUNT(DISTINCT s.IMPORT_QUEUE_ID) AS sales_count,
                SUM(COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0)) AS managerial_total,
                SUM(COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0)) AS commercial_total
             FROM sales_nexus s
             WHERE 1 = 1{$where}
             GROUP BY COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), ''), 'Sem base grupo')
             ORDER BY sales_count DESC, base_group_name ASC
             LIMIT :limit"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function detailed(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');

        $countStatement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM sales_nexus s WHERE 1 = 1{$where}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $this->saleRows($filters, $perPage, $offset);

        return [
            'items' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    public function exportRows(array $filters): array
    {
        return $this->saleRows($filters, null, null);
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function exportDataset(array $filters): array
    {
        $headers = $this->exportColumns();

        if ($headers === []) {
            return [
                'headers' => [],
                'rows' => [],
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $this->rawSaleRows($filters, $headers),
        ];
    }

    public function latestDataTimestamp(array $filters): ?string
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');
        $statement = Database::connection()->prepare(
            "SELECT MAX(COALESCE(s.ATUALIZADO_EM, s.CRIADO_EM)) FROM sales_nexus s WHERE 1 = 1{$where}"
        );
        $statement->execute($bindings);
        $timestamp = $statement->fetchColumn();

        return is_string($timestamp) && trim($timestamp) !== '' ? $timestamp : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function saleRows(array $filters, ?int $limit, ?int $offset): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');

        $sql = "SELECT
                    s.ID,
                    s.IMPORT_QUEUE_ID,
                    s.VENDA_ID AS sale_code,
                    s.VENDA_DATA_INPUT AS sale_input_date,
                    s.POSVENDA_STATUS AS sale_status,
                    s.POSVENDA_SUB_STATUS AS sale_sub_status,
                    COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_NOME), ''), 'Sem operação') AS operation_name,
                    COALESCE(NULLIF(TRIM(s.CONSULTOR_BASE_GRUPO), ''), 'Sem base grupo') AS base_group_name,
                    s.CLIENTE_TIPO_DOCUMENTO AS customer_type,
                    s.CLIENTE_NOME_RAZAO_SOCIAL AS customer_name,
                    s.CONSULTOR_NOME AS consultant_name,
                    s.AUDITOR_NOME AS auditor_name,
                    s.VENDA_MODALIDADE AS modality,
                    COALESCE(NULLIF(TRIM(s.PRODUTO_NOME), ''), '-') AS products,
                    COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0) AS managerial_total,
                    COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0) AS commercial_total,
                    COALESCE(s.ATUALIZADO_EM, s.CRIADO_EM) AS updated_at
                FROM sales_nexus s
                WHERE 1 = 1{$where}
                ORDER BY s.VENDA_DATA_INPUT ASC,
                         s.CLIENTE_NOME_RAZAO_SOCIAL ASC,
                         s.ID ASC";

        $statement = Database::connection()->prepare(
            $limit !== null && $offset !== null
                ? $sql . ' LIMIT :limit OFFSET :offset'
                : $sql
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        if ($limit !== null && $offset !== null) {
            $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryByGroupedSales(array $filters, string $groupExpression, string $alias, int $limit): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');
        $limit = max(1, $limit);

        $statement = Database::connection()->prepare(
            "SELECT
                {$groupExpression} AS {$alias},
                COUNT(DISTINCT s.IMPORT_QUEUE_ID) AS sales_count,
                COUNT(DISTINCT CASE
                    WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2C'
                     AND TRIM(COALESCE(s.PRODUTO_TIPO, '')) = 'DADOS'
                    THEN s.IMPORT_QUEUE_ID
                END) AS ftth_b2c_sales,
                COUNT(DISTINCT CASE
                    WHEN s.CLIENTE_TIPO_DOCUMENTO = 'B2B'
                     AND TRIM(COALESCE(s.PRODUTO_TIPO, '')) = 'DADOS'
                    THEN s.IMPORT_QUEUE_ID
                END) AS ftth_b2b_sales,
                SUM(COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0)) AS managerial_total,
                SUM(COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0)) AS commercial_total
             FROM sales_nexus s
             WHERE 1 = 1{$where}
             GROUP BY {$groupExpression}
             ORDER BY sales_count DESC, {$alias} ASC
             LIMIT :limit"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryByColumn(array $filters, string $groupExpression, string $alias, int $limit): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');
        $limit = max(1, $limit);

        $statement = Database::connection()->prepare(
            "SELECT
                {$groupExpression} AS {$alias},
                COUNT(DISTINCT s.IMPORT_QUEUE_ID) AS sales_count,
                SUM(COALESCE(s.PRODUTO_VALOR_GERENCIAL, 0)) AS managerial_total,
                SUM(COALESCE(s.PRODUTO_PONTUACAO_COMERCIAL, 0)) AS commercial_total
             FROM sales_nexus s
             WHERE 1 = 1{$where}
             GROUP BY {$groupExpression}
             ORDER BY sales_count DESC, {$alias} ASC
             LIMIT :limit"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<int, string>
     */
    private function exportColumns(): array
    {
        $statement = Database::connection()->query('SHOW COLUMNS FROM sales_nexus');
        $excludedColumns = ['CRIADO_EM', 'ATUALIZADO_EM', 'IMPORT_QUEUE_ID', 'USUARIO_FINALIZACAO_ID'];
        $headers = [];

        foreach ($statement->fetchAll() as $column) {
            $field = strtoupper(trim((string) ($column['Field'] ?? '')));

            if ($field === '' || in_array($field, $excludedColumns, true)) {
                continue;
            }

            $headers[] = $field;
        }

        return $headers;
    }

    /**
     * @param array<int, string> $headers
     * @return array<int, array<string, mixed>>
     */
    private function rawSaleRows(array $filters, array $headers): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');
        $selectColumns = implode(', ', array_map(
            static fn (string $header): string => 's.`' . str_replace('`', '``', $header) . '`',
            $headers
        ));

        $statement = Database::connection()->prepare(
            "SELECT {$selectColumns}
             FROM sales_nexus s
             WHERE 1 = 1{$where}
             ORDER BY s.VENDA_DATA_INPUT ASC,
                      s.CLIENTE_NOME_RAZAO_SOCIAL ASC,
                      s.ID ASC"
        );

        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $bindings
     */
    private function buildWhereClause(array $filters, array &$bindings, string $alias, array $exclude = []): string
    {
        $where = '';
        $excluded = array_fill_keys($exclude, true);
        $year = (int) ($filters['year'] ?? 0);

        if ($year > 0) {
            $where .= " AND YEAR({$alias}.VENDA_DATA_INPUT) = :report_year";
            $bindings['report_year'] = $year;
        }

        $months = array_values(array_filter(array_unique(array_map(
            static fn (mixed $month): string => preg_match('/^(0[1-9]|1[0-2])$/', (string) $month) ? (string) $month : '',
            (array) ($filters['months'] ?? $filters['month'] ?? [])
        ))));

        if ($months !== []) {
            $placeholders = [];

            foreach ($months as $index => $month) {
                $key = 'report_month_' . $index;
                $bindings[$key] = $month;
                $placeholders[] = ':' . $key;
            }

            $where .= " AND DATE_FORMAT({$alias}.VENDA_DATA_INPUT, '%m') IN (" . implode(', ', $placeholders) . ')';
        }

        $day = preg_match('/^(0[1-9]|[12][0-9]|3[01])$/', (string) ($filters['day'] ?? ''))
            ? (string) $filters['day']
            : '';

        if ($day !== '') {
            $where .= " AND DATE_FORMAT({$alias}.VENDA_DATA_INPUT, '%d') = :report_day";
            $bindings['report_day'] = $day;
        }

        if (! isset($excluded['statuses'])) {
            $where .= $this->inClause($alias . '.POSVENDA_STATUS', 'status', $filters['statuses'] ?? [], $bindings);
        }
        if (! isset($excluded['sub_statuses'])) {
            $where .= $this->inClause($alias . '.POSVENDA_SUB_STATUS', 'sub_status', $filters['sub_statuses'] ?? [], $bindings);
        }
        if (! isset($excluded['operations'])) {
            $where .= $this->inClause($alias . '.CONSULTOR_BASE_NOME', 'operation', $filters['operations'] ?? [], $bindings);
        }
        if (! isset($excluded['base_groups'])) {
            $where .= $this->inClause($alias . '.CONSULTOR_BASE_GRUPO', 'base_group', $filters['base_groups'] ?? [], $bindings);
        }
        if (! isset($excluded['customer_types'])) {
            $where .= $this->inClause($alias . '.CLIENTE_TIPO_DOCUMENTO', 'customer_type', $filters['customer_types'] ?? [], $bindings);
        }
        if (! isset($excluded['supervisors'])) {
            $where .= $this->inClause($alias . '.SUPERVISOR_NOME', 'supervisor', $filters['supervisors'] ?? [], $bindings);
        }
        if (! isset($excluded['coordinators'])) {
            $where .= $this->inClause($alias . '.COORDENADOR_NOME', 'coordinator', $filters['coordinators'] ?? [], $bindings);
        }
        if (! isset($excluded['managers'])) {
            $where .= $this->inClause($alias . '.GERENTE_BASE_NOME', 'manager', $filters['managers'] ?? [], $bindings);
        }
        if (! isset($excluded['consultants'])) {
            $where .= $this->inClause($alias . '.CONSULTOR_NOME', 'consultant', $filters['consultants'] ?? [], $bindings);
        }
        if (! isset($excluded['territories'])) {
            $where .= $this->inClause($alias . '.CNLAT_TERRITORIO', 'territory', $filters['territories'] ?? [], $bindings);
        }

        if (! isset($excluded['term'])) {
            $term = trim((string) ($filters['term'] ?? ''));
            if ($term !== '') {
                $where .= " AND (
                    {$alias}.CLIENTE_NOME_RAZAO_SOCIAL LIKE :report_term OR
                    {$alias}.VENDA_ID LIKE :report_term OR
                    {$alias}.VENDA_ORDEM_DE_SERVICO LIKE :report_term
                )";
                $bindings['report_term'] = '%' . $term . '%';
            }
        }

        return $where;
    }

    /**
     * @param array<string, mixed> $bindings
     * @param array<int, string> $values
     */
    private function inClause(string $column, string $prefix, array $values, array &$bindings): string
    {
        $normalizedValues = array_values(array_filter(array_unique(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        )), static fn (string $value): bool => $value !== ''));

        if ($normalizedValues === []) {
            return '';
        }

        $placeholders = [];

        foreach ($normalizedValues as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $bindings[$key] = $value;
        }

        return " AND TRIM(COALESCE({$column}, '')) IN (" . implode(', ', $placeholders) . ')';
    }

    /**
     * @return array<int, string>
     */
    private function distinctColumnValues(string $column): array
    {
        $statement = Database::connection()->query(
            "SELECT DISTINCT {$column} AS option_value
             FROM sales_nexus
             WHERE {$column} IS NOT NULL
               AND TRIM({$column}) <> ''
             ORDER BY option_value ASC"
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['option_value'] ?? '')),
            $statement->fetchAll()
        ), static fn (string $value): bool => $value !== ''));
    }

    private function distinctColumnValuesFiltered(string $column, array $filters, array $exclude): array
    {
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's', $exclude);
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT {$column} AS option_value
             FROM sales_nexus s
             WHERE {$column} IS NOT NULL
               AND TRIM({$column}) <> ''{$where}
             ORDER BY option_value ASC"
        );
        $statement->execute($bindings);

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['option_value'] ?? '')),
            $statement->fetchAll()
        ), static fn (string $value): bool => $value !== ''));
    }

    private function mergeSelectedOptions(array $options, array $selected): array
    {
        $normalized = [];
        $result = [];

        foreach ($options as $option) {
            $key = trim((string) $option);
            if ($key === '' || isset($normalized[$key])) {
                continue;
            }
            $normalized[$key] = true;
            $result[] = (string) $option;
        }

        foreach ($selected as $option) {
            $key = trim((string) $option);
            if ($key === '' || isset($normalized[$key])) {
                continue;
            }
            $normalized[$key] = true;
            $result[] = (string) $option;
        }

        return $result;
    }
}
