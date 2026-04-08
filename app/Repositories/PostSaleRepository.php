<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PostSaleRepository
{
    public function latestPeriod(): string
    {
        $statement = Database::connection()->query(
            "SELECT MAX(VENDA_PERIODO_INPUT) AS period_value
             FROM sales_nexus
             WHERE VENDA_PERIODO_INPUT IS NOT NULL
               AND TRIM(VENDA_PERIODO_INPUT) <> ''"
        );

        $period = (string) ($statement->fetchColumn() ?: '');

        return trim($period);
    }

    /**
     * @return array<int, string>
     */
    public function availablePeriods(): array
    {
        $statement = Database::connection()->query(
            "SELECT DISTINCT VENDA_PERIODO_INPUT AS period_value
             FROM sales_nexus
             WHERE VENDA_PERIODO_INPUT IS NOT NULL
               AND TRIM(VENDA_PERIODO_INPUT) <> ''
             ORDER BY period_value DESC"
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['period_value'] ?? '')),
            $statement->fetchAll()
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function detailed(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $bindings = [];
        $where = $this->buildWhereClause($filters, $bindings, 's');

        $countStatement = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_rows
             FROM sales_nexus s
             WHERE 1 = 1{$where}"
        );
        $countStatement->execute($bindings);
        $total = (int) ($countStatement->fetchColumn() ?: 0);

        $offset = ($page - 1) * $perPage;
        $statement = Database::connection()->prepare(
            "SELECT
                s.VENDA_ID AS sale_code,
                s.VENDA_DATA_INPUT AS sale_input_date,
                s.POSVENDA_STATUS AS sale_status,
                s.POSVENDA_SUB_STATUS AS sale_sub_status,
                s.CONSULTOR_BASE_NOME AS operation_name,
                s.CONSULTOR_BASE_GRUPO AS base_group_name,
                s.CLIENTE_TIPO_DOCUMENTO AS customer_type,
                s.CLIENTE_NOME_RAZAO_SOCIAL AS customer_name,
                s.CONSULTOR_NOME AS consultant_name,
                s.AUDITOR_NOME AS auditor_name,
                s.PRODUTO_VALOR_GERENCIAL AS managerial_total,
                s.VENDA_ORDEM_DE_SERVICO AS service_order,
                s.CLIENTE_DOCUMENTO AS customer_document,
                s.VENDA_PERIODO_INPUT AS period_input
             FROM sales_nexus s
             WHERE 1 = 1{$where}
             ORDER BY s.VENDA_DATA_INPUT DESC, s.ID DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    private function buildWhereClause(array $filters, array &$bindings, string $alias): string
    {
        $where = '';
        $term = trim((string) ($filters['term'] ?? ''));

        if ($term !== '') {
            $where .= " AND (
                {$alias}.VENDA_ORDEM_DE_SERVICO LIKE :post_sale_term OR
                {$alias}.CLIENTE_DOCUMENTO LIKE :post_sale_term
            )";
            $bindings['post_sale_term'] = '%' . $term . '%';

            return $where;
        }

        $period = trim((string) ($filters['period'] ?? ''));
        if ($period !== '') {
            $where .= " AND {$alias}.VENDA_PERIODO_INPUT = :post_sale_period";
            $bindings['post_sale_period'] = $period;
        }

        return $where;
    }
}
