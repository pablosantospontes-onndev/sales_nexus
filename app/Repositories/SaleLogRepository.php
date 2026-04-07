<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SaleLogRepository
{
    public function logAccess(array $queueItem, int $userId, ?PDO $connection = null): void
    {
        $this->create(
            (string) ($queueItem['sale_code'] ?? ''),
            isset($queueItem['id']) ? (int) $queueItem['id'] : null,
            $userId,
            'ACESSO',
            'Venda aberta para auditoria.',
            [],
            $connection
        );
    }

    public function logChanges(array $queueItem, int $userId, array $changes, ?PDO $connection = null): void
    {
        if ($changes === []) {
            return;
        }

        $this->create(
            (string) ($queueItem['sale_code'] ?? ''),
            isset($queueItem['id']) ? (int) $queueItem['id'] : null,
            $userId,
            'ALTERACAO',
            $this->buildChangeSummary($changes),
            $changes,
            $connection
        );
    }

    public function logFinalization(array $queueItem, int $userId, int $productCount, ?PDO $connection = null): void
    {
        $this->create(
            (string) ($queueItem['sale_code'] ?? ''),
            isset($queueItem['id']) ? (int) $queueItem['id'] : null,
            $userId,
            'FINALIZACAO',
            sprintf('Venda finalizada com %d produto(s).', $productCount),
            [],
            $connection
        );
    }

    public function bySaleCode(string $saleCode): array
    {
        $statement = Database::connection()->prepare(
            'SELECT sale_logs.*, users.name AS user_name
             FROM sale_logs
             LEFT JOIN users ON users.id = sale_logs.user_id
             WHERE sale_logs.sale_code = :sale_code
               AND sale_logs.action_type <> \'ACESSO\'
             ORDER BY sale_logs.created_at DESC, sale_logs.id DESC'
        );
        $statement->execute(['sale_code' => $saleCode]);

        return array_map(function (array $log): array {
            $decodedChanges = json_decode((string) ($log['changed_fields_json'] ?? ''), true);
            $log['changes'] = is_array($decodedChanges) ? $decodedChanges : [];

            return $log;
        }, $statement->fetchAll());
    }

    private function create(
        string $saleCode,
        ?int $queueId,
        int $userId,
        string $actionType,
        string $summary,
        array $changes = [],
        ?PDO $connection = null
    ): void {
        $resolvedConnection = $connection ?? Database::connection();
        $statement = $resolvedConnection->prepare(
            'INSERT INTO sale_logs (
                sale_code,
                import_queue_id,
                user_id,
                action_type,
                summary,
                changed_fields_json
            ) VALUES (
                :sale_code,
                :import_queue_id,
                :user_id,
                :action_type,
                :summary,
                :changed_fields_json
            )'
        );
        $statement->execute([
            'sale_code' => $saleCode,
            'import_queue_id' => $queueId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'summary' => $summary,
            'changed_fields_json' => $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function buildChangeSummary(array $changes): string
    {
        $fields = array_values(array_unique(array_filter(array_map(
            static fn (array $change): string => normalize_text($change['field'] ?? ''),
            $changes
        ))));

        if ($fields === []) {
            return 'Campos alterados na venda.';
        }

        return 'Campos alterados: ' . implode(', ', $fields) . '.';
    }
}
