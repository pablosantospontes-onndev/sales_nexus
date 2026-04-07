<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ProductRepository
{
    private const ALLOWED_DOCUMENT_TYPES = ['B2B', 'B2C'];

    private const SELECT_FIELDS = '
        id,
        `PRODUTO_NOME`,
        `PRODUTO_VALOR_GERENCIAL`,
        `PRODUTO_PONTUACAO_COMERCIAL`,
        `FATOR`,
        `CATEGORIA`,
        `TIPO`,
        `VIVO_TOTAL`,
        `TIPO_DOC`,
        `SOLO`,
        `2P`,
        `3P`,
        `DUO`,
        `PERIODO`,
        `ativo`,
        created_at,
        updated_at,
        `PRODUTO_NOME` AS name,
        `PRODUTO_VALOR_GERENCIAL` AS managerial_value,
        `PRODUTO_PONTUACAO_COMERCIAL` AS commercial_score,
        `CATEGORIA` AS category,
        `TIPO` AS type,
        `TIPO_DOC` AS document_type
    ';

    public function stats(): array
    {
        $connection = Database::connection();

        return [
            'total' => (int) $connection->query('SELECT COUNT(*) FROM catalog_products')->fetchColumn(),
            'active' => (int) $connection->query('SELECT COUNT(*) FROM catalog_products WHERE ativo = 1')->fetchColumn(),
            'b2b' => (int) $connection->query("SELECT COUNT(*) FROM catalog_products WHERE ativo = 1 AND `TIPO_DOC` = 'B2B'")->fetchColumn(),
            'b2c' => (int) $connection->query("SELECT COUNT(*) FROM catalog_products WHERE ativo = 1 AND `TIPO_DOC` = 'B2C'")->fetchColumn(),
        ];
    }

    public function all(
        ?string $term = null,
        ?string $documentType = null,
        ?string $category = null,
        ?string $vivoTotal = null
    ): array
    {
        $sql = 'SELECT ' . self::SELECT_FIELDS . ' FROM catalog_products WHERE 1 = 1';
        $bindings = [];

        if ($term !== null && trim($term) !== '') {
            $normalizedTerm = '%' . strtoupper(normalize_text($term)) . '%';
            $sql .= ' AND (
                `PRODUTO_NOME` LIKE :term OR
                `TIPO` LIKE :term OR
                `CATEGORIA` LIKE :term OR
                `TIPO_DOC` LIKE :term OR
                `PERIODO` LIKE :term
            )';
            $bindings['term'] = $normalizedTerm;
        }

        $normalizedDocumentType = $this->normalizeDocumentType($documentType);
        if ($normalizedDocumentType !== null) {
            $sql .= ' AND `TIPO_DOC` = :document_type';
            $bindings['document_type'] = $normalizedDocumentType;
        }

        $normalizedCategory = $this->normalizeUpperText($category);
        if ($normalizedCategory !== null) {
            $sql .= ' AND `CATEGORIA` = :category';
            $bindings['category'] = $normalizedCategory;
        }

        $normalizedVivoTotal = $this->normalizeUpperText($vivoTotal);
        if ($normalizedVivoTotal !== null) {
            $sql .= ' AND UPPER(COALESCE(`VIVO_TOTAL`, \'\')) = :vivo_total';
            $bindings['vivo_total'] = $normalizedVivoTotal;
        }

        $sql .= ' ORDER BY ativo DESC, `PERIODO` DESC, `PRODUTO_NOME` ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function categories(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT `CATEGORIA`
             FROM catalog_products
             WHERE `CATEGORIA` IS NOT NULL
               AND `CATEGORIA` <> ""
             ORDER BY `CATEGORIA` ASC'
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['CATEGORIA'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::SELECT_FIELDS . '
             FROM catalog_products
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function periods(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT `PERIODO`
             FROM catalog_products
             WHERE `PERIODO` IS NOT NULL
               AND `PERIODO` <> ""
             ORDER BY `PERIODO` DESC'
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['PERIODO'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function currentPeriod(): string
    {
        return date('Ym');
    }

    public function duplicateSourcePeriod(string $targetPeriod): ?string
    {
        $normalizedTargetPeriod = $this->normalizePeriod($targetPeriod);

        if ($normalizedTargetPeriod === null) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT MAX(`PERIODO`)
             FROM catalog_products
             WHERE `PERIODO` < :target_period'
        );
        $statement->execute(['target_period' => $normalizedTargetPeriod]);
        $sourcePeriod = $statement->fetchColumn();

        return is_string($sourcePeriod) && preg_match('/^\d{6}$/', $sourcePeriod) ? $sourcePeriod : null;
    }

    public function hasCatalogForPeriod(string $period): bool
    {
        $normalizedPeriod = $this->normalizePeriod($period);

        if ($normalizedPeriod === null) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM catalog_products
             WHERE `PERIODO` = :period'
        );
        $statement->execute(['period' => $normalizedPeriod]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function allByPeriod(string $period): array
    {
        $normalizedPeriod = $this->normalizePeriod($period);

        if ($normalizedPeriod === null) {
            throw new RuntimeException('Selecione um período válido para exportar.');
        }

        $statement = Database::connection()->prepare(
            'SELECT ' . self::SELECT_FIELDS . '
             FROM catalog_products
             WHERE `PERIODO` = :period
             ORDER BY `TIPO_DOC` ASC, `PRODUTO_NOME` ASC'
        );
        $statement->execute(['period' => $normalizedPeriod]);

        return $statement->fetchAll();
    }

    public function allActive(): array
    {
        return $this->allActiveByFilters();
    }

    public function allActiveByContext(?string $documentType, ?string $period): array
    {
        return $this->allActiveByFilters($documentType, $this->resolveActivePeriodFilter($documentType, $period));
    }

    public function findByIds(array $ids): array
    {
        return $this->findByIdsInContext($ids, null, null);
    }

    public function findByIdsInContext(array $ids, ?string $documentType, ?string $period): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT ' . self::SELECT_FIELDS . " FROM catalog_products WHERE id IN ({$placeholders}) AND ativo = 1";
        $bindings = $ids;

        $normalizedDocumentType = $this->normalizeDocumentType($documentType);
        if ($normalizedDocumentType !== null) {
            $sql .= ' AND `TIPO_DOC` = ?';
            $bindings[] = $normalizedDocumentType;
        }

        $normalizedPeriod = $this->resolveActivePeriodFilter($documentType, $period);
        if ($normalizedPeriod !== null) {
            $sql .= ' AND `PERIODO` = ?';
            $bindings[] = $normalizedPeriod;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        $products = [];

        foreach ($statement->fetchAll() as $product) {
            $products[(int) $product['id']] = $product;
        }

        return $products;
    }

    public function idsByNames(array $names, ?string $documentType = null, ?string $period = null): array
    {
        $normalizedNames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $name): string => normalize_text((string) $name),
            $names
        ))));

        if ($normalizedNames === []) {
            return [];
        }

        $products = $this->allActiveByFilters($documentType, $this->resolveActivePeriodFilter($documentType, $period));
        $productIds = [];
        $nameIndex = [];

        foreach ($products as $product) {
            $nameIndex[ascii_key((string) ($product['name'] ?? ''))] = (int) ($product['id'] ?? 0);
        }

        foreach ($normalizedNames as $name) {
            $productId = $nameIndex[ascii_key($name)] ?? null;

            if ($productId !== null) {
                $productIds[] = $productId;
            }
        }

        return array_values(array_unique($productIds));
    }

    public function save(?int $id, array $data, array $syncOptions = []): array
    {
        $payload = $this->normalizePayload($data);
        $existingProduct = $id !== null && $id > 0 ? $this->findById($id) : null;

        if ($id !== null && $id > 0 && $existingProduct === null) {
            throw new RuntimeException('Produto não encontrado.');
        }

        $duplicateProduct = $this->findByIdentity($payload['PRODUTO_NOME'], $payload['TIPO_DOC'], $payload['PERIODO']);
        if ($duplicateProduct !== null && (int) $duplicateProduct['id'] !== (int) $id) {
            throw new RuntimeException('Já existe um produto cadastrado com esse nome, tipo de documento e período.');
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $syncedSales = 0;
            $normalizedSyncOptions = $this->normalizeSyncOptions($syncOptions);

            if ($existingProduct !== null) {
                $statement = $connection->prepare(
                    'UPDATE catalog_products
                     SET `PRODUTO_NOME` = :PRODUTO_NOME,
                         `PRODUTO_VALOR_GERENCIAL` = :PRODUTO_VALOR_GERENCIAL,
                         `PRODUTO_PONTUACAO_COMERCIAL` = :PRODUTO_PONTUACAO_COMERCIAL,
                         `FATOR` = :FATOR,
                         `CATEGORIA` = :CATEGORIA,
                         `TIPO` = :TIPO,
                         `VIVO_TOTAL` = :VIVO_TOTAL,
                         `TIPO_DOC` = :TIPO_DOC,
                         `SOLO` = :SOLO,
                         `2P` = :TWO_P,
                         `3P` = :THREE_P,
                         `DUO` = :DUO,
                         `PERIODO` = :PERIODO,
                         `ativo` = :ativo
                     WHERE id = :id'
                );
                $statement->execute($this->catalogBindings($payload) + ['id' => $id]);

                if ($normalizedSyncOptions['mode'] === 'recalculate') {
                    $syncedSales = $this->syncSalesNexus(
                        $existingProduct,
                        $payload,
                        $connection,
                        $normalizedSyncOptions['date_from'],
                        $normalizedSyncOptions['date_to']
                    );
                }

                $productId = $id;
            } else {
                $statement = $connection->prepare(
                    'INSERT INTO catalog_products (
                        `PRODUTO_NOME`,
                        `PRODUTO_VALOR_GERENCIAL`,
                        `PRODUTO_PONTUACAO_COMERCIAL`,
                        `FATOR`,
                        `CATEGORIA`,
                        `TIPO`,
                        `VIVO_TOTAL`,
                        `TIPO_DOC`,
                        `SOLO`,
                        `2P`,
                        `3P`,
                        `DUO`,
                        `PERIODO`,
                        `ativo`
                    ) VALUES (
                        :PRODUTO_NOME,
                        :PRODUTO_VALOR_GERENCIAL,
                        :PRODUTO_PONTUACAO_COMERCIAL,
                        :FATOR,
                        :CATEGORIA,
                        :TIPO,
                        :VIVO_TOTAL,
                        :TIPO_DOC,
                        :SOLO,
                        :TWO_P,
                        :THREE_P,
                        :DUO,
                        :PERIODO,
                        :ativo
                    )'
                );
                $statement->execute($this->catalogBindings($payload));
                $productId = (int) $connection->lastInsertId();
            }

            $connection->commit();

            return [
                'id' => $productId,
                'synced_sales' => $syncedSales,
                'recalculated' => $normalizedSyncOptions['mode'] === 'recalculate',
                'recalculation_date_from' => $normalizedSyncOptions['date_from'],
                'recalculation_date_to' => $normalizedSyncOptions['date_to'],
            ];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function importRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('A planilha não possui linhas válidas para importar.');
        }

        $preparedRowsByKey = [];
        $duplicateKeys = [];

        foreach ($rows as $row) {
            $payload = $this->normalizeSpreadsheetPayload($row);
            $identityKey = $payload['PRODUTO_NOME'] . '|' . $payload['TIPO_DOC'] . '|' . $payload['PERIODO'];

            if (isset($preparedRowsByKey[$identityKey])) {
                $duplicateKeys[$identityKey] = true;
            }

            $preparedRowsByKey[$identityKey] = $payload;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $selectStatement = $connection->prepare(
                'SELECT id
                 FROM catalog_products
                 WHERE `PRODUTO_NOME` = :product_name
                   AND `TIPO_DOC` = :document_type
                   AND `PERIODO` = :period
                 LIMIT 1'
            );
            $insertStatement = $connection->prepare(
                'INSERT INTO catalog_products (
                    `PRODUTO_NOME`,
                    `PRODUTO_VALOR_GERENCIAL`,
                    `PRODUTO_PONTUACAO_COMERCIAL`,
                    `FATOR`,
                    `CATEGORIA`,
                    `TIPO`,
                    `VIVO_TOTAL`,
                    `TIPO_DOC`,
                    `SOLO`,
                    `2P`,
                    `3P`,
                    `DUO`,
                    `PERIODO`,
                    `ativo`
                ) VALUES (
                    :PRODUTO_NOME,
                    :PRODUTO_VALOR_GERENCIAL,
                    :PRODUTO_PONTUACAO_COMERCIAL,
                    :FATOR,
                    :CATEGORIA,
                    :TIPO,
                    :VIVO_TOTAL,
                    :TIPO_DOC,
                    :SOLO,
                    :TWO_P,
                    :THREE_P,
                    :DUO,
                    :PERIODO,
                    :ativo
                )'
            );
            $updateStatement = $connection->prepare(
                'UPDATE catalog_products
                 SET `PRODUTO_NOME` = :PRODUTO_NOME,
                     `PRODUTO_VALOR_GERENCIAL` = :PRODUTO_VALOR_GERENCIAL,
                     `PRODUTO_PONTUACAO_COMERCIAL` = :PRODUTO_PONTUACAO_COMERCIAL,
                     `FATOR` = :FATOR,
                     `CATEGORIA` = :CATEGORIA,
                     `TIPO` = :TIPO,
                     `VIVO_TOTAL` = :VIVO_TOTAL,
                     `TIPO_DOC` = :TIPO_DOC,
                     `SOLO` = :SOLO,
                     `2P` = :TWO_P,
                     `3P` = :THREE_P,
                     `DUO` = :DUO,
                     `PERIODO` = :PERIODO,
                     `ativo` = :ativo
                 WHERE id = :id'
            );

            $inserted = 0;
            $updated = 0;

            foreach ($preparedRowsByKey as $payload) {
                $selectStatement->execute([
                    'product_name' => $payload['PRODUTO_NOME'],
                    'document_type' => $payload['TIPO_DOC'],
                    'period' => $payload['PERIODO'],
                ]);
                $existingId = (int) ($selectStatement->fetchColumn() ?: 0);

                if ($existingId > 0) {
                    $updateStatement->execute($this->catalogBindings($payload) + ['id' => $existingId]);
                    $updated++;
                    continue;
                }

                $insertStatement->execute($this->catalogBindings($payload));
                $inserted++;
            }

            $connection->commit();

            return [
                'processed' => count($preparedRowsByKey),
                'inserted' => $inserted,
                'updated' => $updated,
                'duplicate_keys' => count($duplicateKeys),
            ];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function duplicateCatalogToCurrentPeriod(): array
    {
        $targetPeriod = $this->currentPeriod();
        $sourcePeriod = $this->duplicateSourcePeriod($targetPeriod);

        if ($sourcePeriod === null) {
            throw new RuntimeException("N\u{00E3}o existe um per\u{00ED}odo anterior dispon\u{00ED}vel para duplicar o cat\u{00E1}logo.");
        }

        if ($this->hasCatalogForPeriod($targetPeriod)) {
            throw new RuntimeException("J\u{00E1} existe um cat\u{00E1}logo cadastrado para o per\u{00ED}odo atual.");
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $insertStatement = $connection->prepare(
                'INSERT INTO catalog_products (
                    `PRODUTO_NOME`,
                    `PRODUTO_VALOR_GERENCIAL`,
                    `PRODUTO_PONTUACAO_COMERCIAL`,
                    `FATOR`,
                    `CATEGORIA`,
                    `TIPO`,
                    `VIVO_TOTAL`,
                    `TIPO_DOC`,
                    `SOLO`,
                    `2P`,
                    `3P`,
                    `DUO`,
                    `PERIODO`,
                    `ativo`
                )
                SELECT
                    `PRODUTO_NOME`,
                    `PRODUTO_VALOR_GERENCIAL`,
                    `PRODUTO_PONTUACAO_COMERCIAL`,
                    `FATOR`,
                    `CATEGORIA`,
                    `TIPO`,
                    `VIVO_TOTAL`,
                    `TIPO_DOC`,
                    `SOLO`,
                    `2P`,
                    `3P`,
                    `DUO`,
                    :target_period,
                    `ativo`
                FROM catalog_products
                WHERE `PERIODO` = :source_period'
            );
            $insertStatement->execute([
                'source_period' => $sourcePeriod,
                'target_period' => $targetPeriod,
            ]);
            $inserted = $insertStatement->rowCount();

            if ($inserted <= 0) {
                throw new RuntimeException("Nenhum produto foi encontrado no per\u{00ED}odo anterior para duplicar.");
            }

            $connection->commit();

            return [
                'source_period' => $sourcePeriod,
                'target_period' => $targetPeriod,
                'inserted' => $inserted,
            ];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    private function allActiveByFilters(?string $documentType = null, ?string $period = null): array
    {
        $sql = 'SELECT ' . self::SELECT_FIELDS . ' FROM catalog_products WHERE ativo = 1';
        $bindings = [];

        $normalizedDocumentType = $this->normalizeDocumentType($documentType);
        if ($normalizedDocumentType !== null) {
            $sql .= ' AND `TIPO_DOC` = :document_type';
            $bindings['document_type'] = $normalizedDocumentType;
        }

        $normalizedPeriod = $this->normalizePeriod($period);
        if ($normalizedPeriod !== null) {
            $sql .= ' AND `PERIODO` = :period';
            $bindings['period'] = $normalizedPeriod;
        }

        $sql .= ' ORDER BY `PRODUTO_NOME` ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    private function resolveActivePeriodFilter(?string $documentType, ?string $period): ?string
    {
        $normalizedPeriod = $this->normalizePeriod($period);

        if ($normalizedPeriod === null) {
            return null;
        }

        if ($this->hasActiveProductsForPeriod($documentType, $normalizedPeriod)) {
            return $normalizedPeriod;
        }

        return $this->latestActivePeriod($documentType);
    }

    private function hasActiveProductsForPeriod(?string $documentType, string $period): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM catalog_products
                WHERE ativo = 1
                  AND `PERIODO` = :period';
        $bindings = ['period' => $period];
        $normalizedDocumentType = $this->normalizeDocumentType($documentType);

        if ($normalizedDocumentType !== null) {
            $sql .= ' AND `TIPO_DOC` = :document_type';
            $bindings['document_type'] = $normalizedDocumentType;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return (int) $statement->fetchColumn() > 0;
    }

    private function latestActivePeriod(?string $documentType): ?string
    {
        $sql = 'SELECT MAX(`PERIODO`) FROM catalog_products WHERE ativo = 1';
        $bindings = [];
        $normalizedDocumentType = $this->normalizeDocumentType($documentType);

        if ($normalizedDocumentType !== null) {
            $sql .= ' AND `TIPO_DOC` = :document_type';
            $bindings['document_type'] = $normalizedDocumentType;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);
        $period = $statement->fetchColumn();

        return is_string($period) && preg_match('/^\d{6}$/', $period) ? $period : null;
    }

    private function findByIdentity(string $productName, string $documentType, string $period): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::SELECT_FIELDS . '
             FROM catalog_products
             WHERE `PRODUTO_NOME` = :product_name
               AND `TIPO_DOC` = :document_type
               AND `PERIODO` = :period
             LIMIT 1'
        );
        $statement->execute([
            'product_name' => $productName,
            'document_type' => $documentType,
            'period' => $period,
        ]);

        return $statement->fetch() ?: null;
    }

    private function syncSalesNexus(
        array $originalProduct,
        array $payload,
        PDO $connection,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int
    {
        $sql = 'UPDATE sales_nexus
                SET PRODUTO_NOME = :new_product_name,
                    PRODUTO_PONTUACAO_COMERCIAL = :new_commercial_score,
                    PRODUTO_TIPO = :new_type,
                    PRODUTO_CATEGORIA = :new_category,
                    PRODUTO_NOME_TIPO = :new_document_type,
                    PRODUTO_VALOR_GERENCIAL = :new_managerial_value
                WHERE PRODUTO_NOME = :current_product_name
                  AND VENDA_PERIODO_INPUT = :current_period
                  AND COALESCE(PRODUTO_NOME_TIPO, \'\') = :current_document_type';

        $bindings = [
            'new_product_name' => $payload['PRODUTO_NOME'],
            'new_commercial_score' => $payload['PRODUTO_PONTUACAO_COMERCIAL'],
            'new_type' => $payload['TIPO'],
            'new_category' => $payload['CATEGORIA'],
            'new_document_type' => $payload['TIPO_DOC'],
            'new_managerial_value' => $payload['PRODUTO_VALOR_GERENCIAL'],
            'current_product_name' => (string) ($originalProduct['PRODUTO_NOME'] ?? ''),
            'current_period' => (string) ($originalProduct['PERIODO'] ?? ''),
            'current_document_type' => (string) ($originalProduct['TIPO_DOC'] ?? ''),
        ];

        if ($dateFrom !== null && $dateTo !== null) {
            $sql .= ' AND VENDA_DATA_INPUT BETWEEN :date_from AND :date_to';
            $bindings['date_from'] = $dateFrom;
            $bindings['date_to'] = $dateTo;
        } elseif ($dateFrom !== null) {
            $sql .= ' AND VENDA_DATA_INPUT >= :date_from';
            $bindings['date_from'] = $dateFrom;
        } elseif ($dateTo !== null) {
            $sql .= ' AND VENDA_DATA_INPUT <= :date_to';
            $bindings['date_to'] = $dateTo;
        }

        $statement = $connection->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    private function normalizeSyncOptions(array $syncOptions): array
    {
        $mode = normalize_text($syncOptions['mode'] ?? '');
        $dateFrom = normalize_date_to_db($syncOptions['date_from'] ?? null);
        $dateTo = normalize_date_to_db($syncOptions['date_to'] ?? null);

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($mode !== 'recalculate') {
            return [
                'mode' => 'save_only',
                'date_from' => null,
                'date_to' => null,
            ];
        }

        if ($dateFrom === null && $dateTo === null) {
            throw new RuntimeException('Selecione a data inicial ou o intervalo para recalcular as vendas finalizadas.');
        }

        return [
            'mode' => 'recalculate',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function normalizePayload(array $data): array
    {
        $productName = $this->normalizeUpperText($data['product_name'] ?? '');
        $productDocumentType = $this->normalizeDocumentType($data['document_type'] ?? null);
        $period = $this->normalizePeriod($data['period'] ?? null);
        $active = $this->normalizeActiveFlag($data['active'] ?? '1');

        if ($productName === null) {
            throw new RuntimeException('Informe o PRODUTO_NOME.');
        }

        if ($productDocumentType === null) {
            throw new RuntimeException('Selecione um TIPO_DOC válido.');
        }

        if ($period === null) {
            throw new RuntimeException('Informe o PERÍODO no formato YYYYMM.');
        }

        return [
            'PRODUTO_NOME' => $productName,
            'PRODUTO_VALOR_GERENCIAL' => $this->normalizeNullableDecimal($data['managerial_value'] ?? null),
            'PRODUTO_PONTUACAO_COMERCIAL' => $this->normalizeNullableDecimal($data['commercial_score'] ?? null),
            'FATOR' => $this->normalizeNullableDecimal($data['factor'] ?? null),
            'CATEGORIA' => $this->normalizeUpperText($data['category'] ?? ''),
            'TIPO' => $this->normalizeUpperText($data['type'] ?? ''),
            'VIVO_TOTAL' => $this->normalizeUpperText($data['vivo_total'] ?? ''),
            'TIPO_DOC' => $productDocumentType,
            'SOLO' => $this->normalizeUpperText($data['solo'] ?? ''),
            '2P' => $this->normalizeUpperText($data['two_p'] ?? ''),
            '3P' => $this->normalizeUpperText($data['three_p'] ?? ''),
            'DUO' => $this->normalizeUpperText($data['duo'] ?? ''),
            'PERIODO' => $period,
            'ativo' => $active,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeSpreadsheetPayload(array $row): array
    {
        return $this->normalizePayload([
            'product_name' => $row['PRODUTO_NOME'] ?? '',
            'managerial_value' => $row['PRODUTO_VALOR_GERENCIAL'] ?? null,
            'commercial_score' => $row['PRODUTO_PONTUACAO_COMERCIAL'] ?? null,
            'factor' => $row['FATOR'] ?? null,
            'category' => $row['CATEGORIA'] ?? '',
            'type' => $row['TIPO'] ?? '',
            'vivo_total' => $row['VIVO_TOTAL'] ?? '',
            'document_type' => $row['TIPO_DOC'] ?? '',
            'solo' => $row['SOLO'] ?? '',
            'two_p' => $row['2P'] ?? '',
            'three_p' => $row['3P'] ?? '',
            'duo' => $row['DUO'] ?? '',
            'period' => $row['PERIODO'] ?? '',
            'active' => $row['ativo'] ?? '1',
        ]);
    }

    private function catalogBindings(array $payload): array
    {
        return [
            'PRODUTO_NOME' => $payload['PRODUTO_NOME'],
            'PRODUTO_VALOR_GERENCIAL' => $payload['PRODUTO_VALOR_GERENCIAL'],
            'PRODUTO_PONTUACAO_COMERCIAL' => $payload['PRODUTO_PONTUACAO_COMERCIAL'],
            'FATOR' => $payload['FATOR'],
            'CATEGORIA' => $payload['CATEGORIA'],
            'TIPO' => $payload['TIPO'],
            'VIVO_TOTAL' => $payload['VIVO_TOTAL'],
            'TIPO_DOC' => $payload['TIPO_DOC'],
            'SOLO' => $payload['SOLO'],
            'TWO_P' => $payload['2P'],
            'THREE_P' => $payload['3P'],
            'DUO' => $payload['DUO'],
            'PERIODO' => $payload['PERIODO'],
            'ativo' => $payload['ativo'],
        ];
    }

    private function normalizeUpperText(?string $value): ?string
    {
        $normalizedValue = mb_strtoupper(normalize_text($value));

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    private function normalizeDocumentType(?string $value): ?string
    {
        $normalizedValue = strtoupper(normalize_text((string) $value));

        return in_array($normalizedValue, self::ALLOWED_DOCUMENT_TYPES, true) ? $normalizedValue : null;
    }

    private function normalizePeriod(?string $value): ?string
    {
        $normalizedValue = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($normalizedValue) === 6 ? $normalizedValue : null;
    }

    private function normalizeNullableDecimal(mixed $value): ?string
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '' || $normalizedValue === '-') {
            return null;
        }

        $normalizedValue = str_replace(',', '.', $normalizedValue);

        if (! is_numeric($normalizedValue)) {
            throw new RuntimeException('Informe apenas números válidos para os campos decimais do produto.');
        }

        return number_format((float) $normalizedValue, 2, '.', '');
    }

    private function normalizeActiveFlag(mixed $value): int
    {
        $normalizedValue = strtoupper(normalize_text((string) $value));

        if (in_array($normalizedValue, ['0', 'NAO', 'NÃO', 'FALSE', 'INATIVO'], true)) {
            return 0;
        }

        return 1;
    }
}
