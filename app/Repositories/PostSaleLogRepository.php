<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PostSaleLogRepository
{
    public function create(array $data): void
    {
        $this->ensureTableExists();
        $statement = Database::connection()->prepare(
            'INSERT INTO post_sales_update_logs (
                user_id,
                original_filename,
                total_rows,
                updated_rows,
                skipped_rows,
                not_found_rows,
                status,
                message
            ) VALUES (
                :user_id,
                :original_filename,
                :total_rows,
                :updated_rows,
                :skipped_rows,
                :not_found_rows,
                :status,
                :message
            )'
        );

        $statement->execute([
            'user_id' => (int) ($data['user_id'] ?? 0),
            'original_filename' => (string) ($data['original_filename'] ?? ''),
            'total_rows' => (int) ($data['total_rows'] ?? 0),
            'updated_rows' => (int) ($data['updated_rows'] ?? 0),
            'skipped_rows' => (int) ($data['skipped_rows'] ?? 0),
            'not_found_rows' => (int) ($data['not_found_rows'] ?? 0),
            'status' => (string) ($data['status'] ?? 'FALHA'),
            'message' => (string) ($data['message'] ?? ''),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 10): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(50, $limit));
        $statement = Database::connection()->prepare(
            'SELECT
                logs.*,
                users.name AS user_name
             FROM post_sales_update_logs logs
             LEFT JOIN users ON users.id = logs.user_id
             ORDER BY logs.created_at DESC, logs.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function ensureTableExists(): void
    {
        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS post_sales_update_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                original_filename VARCHAR(255) DEFAULT NULL,
                total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
                skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
                not_found_rows INT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM('SUCESSO', 'FALHA') NOT NULL DEFAULT 'SUCESSO',
                message VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_post_sales_update_logs_user (user_id),
                CONSTRAINT fk_post_sales_update_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
        );
    }
}
