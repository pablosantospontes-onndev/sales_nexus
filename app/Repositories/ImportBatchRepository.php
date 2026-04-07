<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ImportBatchRepository
{
    public function create(string $originalFilename, string $csvFilename, int $userId): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO import_batches (
                original_filename,
                csv_filename,
                total_rows,
                eligible_rows,
                imported_count,
                duplicate_count,
                filtered_out_count,
                created_by_user_id
            ) VALUES (
                :original_filename,
                :csv_filename,
                0,
                0,
                0,
                0,
                0,
                :created_by_user_id
            )'
        );

        $statement->execute([
            'original_filename' => $originalFilename,
            'csv_filename' => $csvFilename,
            'created_by_user_id' => $userId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateCounters(int $batchId, array $counters): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE import_batches
             SET total_rows = :total_rows,
                 eligible_rows = :eligible_rows,
                 imported_count = :imported_count,
                 duplicate_count = :duplicate_count,
                 filtered_out_count = :filtered_out_count
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $batchId,
            'total_rows' => (int) ($counters['total_rows'] ?? 0),
            'eligible_rows' => (int) ($counters['eligible_rows'] ?? 0),
            'imported_count' => (int) ($counters['imported_count'] ?? 0),
            'duplicate_count' => (int) ($counters['duplicate_count'] ?? 0),
            'filtered_out_count' => (int) ($counters['filtered_out_count'] ?? 0),
        ]);
    }

    public function recent(int $limit = 10): array
    {
        $statement = Database::connection()->prepare(
            'SELECT import_batches.*, users.name AS created_by_name
             FROM import_batches
             LEFT JOIN users ON users.id = import_batches.created_by_user_id
             ORDER BY import_batches.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
