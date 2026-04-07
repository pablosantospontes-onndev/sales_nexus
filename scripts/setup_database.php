<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$schema = file_get_contents(APP_ROOT . '/database/schema.sql');

if ($schema === false) {
    fwrite(STDERR, "Nao foi possivel ler o schema.\n");
    exit(1);
}

$statements = preg_split('/;\s*(\r\n|\r|\n)/', $schema) ?: [];
$connection = Database::connection();

ensureColumn(
    $connection,
    'users',
    'cpf',
    "ALTER TABLE users ADD COLUMN cpf VARCHAR(11) DEFAULT NULL AFTER email"
);

ensureColumn(
    $connection,
    'users',
    'regional_view',
    "ALTER TABLE users ADD COLUMN regional_view ENUM('FULL', 'I', 'II', 'PERSONALIZADO') NOT NULL DEFAULT 'FULL' AFTER role"
);

ensureColumn(
    $connection,
    'users',
    'must_change_password',
    "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash"
);

ensureColumn(
    $connection,
    'users',
    'last_seen_at',
    "ALTER TABLE users ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER last_login_at"
);

ensureColumn(
    $connection,
    'users',
    'current_session_id',
    "ALTER TABLE users ADD COLUMN current_session_id VARCHAR(128) DEFAULT NULL AFTER last_seen_at"
);

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    $connection->exec($statement);
}

recreateLegacyCatalogProductsTable($connection);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'role',
    "ALTER TABLE seller_hierarchies ADD COLUMN role ENUM('CONSULTOR', 'SUPERVISOR', 'COORDENADOR', 'GERENTE') NOT NULL DEFAULT 'CONSULTOR' AFTER seller_cpf"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'PERIODO_HEADCOUNT',
    "ALTER TABLE seller_hierarchies ADD COLUMN PERIODO_HEADCOUNT VARCHAR(6) NOT NULL DEFAULT '202603' AFTER seller_cpf"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'CONSULTOR_BASE_REGIONAL',
    "ALTER TABLE seller_hierarchies ADD COLUMN CONSULTOR_BASE_REGIONAL VARCHAR(150) DEFAULT NULL AFTER base_group_id"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'SUPERVISOR_CPF',
    "ALTER TABLE seller_hierarchies ADD COLUMN SUPERVISOR_CPF VARCHAR(14) DEFAULT NULL AFTER supervisor_name"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'COORDENADOR_CPF',
    "ALTER TABLE seller_hierarchies ADD COLUMN COORDENADOR_CPF VARCHAR(14) DEFAULT NULL AFTER coordinator_name"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'GERENTE_BASE_CPF',
    "ALTER TABLE seller_hierarchies ADD COLUMN GERENTE_BASE_CPF VARCHAR(14) DEFAULT NULL AFTER manager_name"
);

ensureColumn(
    $connection,
    'hierarchy_base_groups',
    'base_id',
    "ALTER TABLE hierarchy_base_groups ADD COLUMN base_id BIGINT UNSIGNED DEFAULT NULL AFTER name"
);

ensureColumn(
    $connection,
    'hierarchy_bases',
    'REGIONAL',
    "ALTER TABLE hierarchy_bases ADD COLUMN REGIONAL ENUM('I', 'II') DEFAULT NULL AFTER name"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'CONSULTOR_SETOR_NOME',
    "ALTER TABLE seller_hierarchies ADD COLUMN CONSULTOR_SETOR_NOME VARCHAR(150) DEFAULT NULL AFTER CONSULTOR_BASE_REGIONAL"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'CONSULTOR_SETOR_TIPO',
    "ALTER TABLE seller_hierarchies ADD COLUMN CONSULTOR_SETOR_TIPO VARCHAR(50) DEFAULT NULL AFTER CONSULTOR_SETOR_NOME"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'GERENTE_TERRITORIO_NOME',
    "ALTER TABLE seller_hierarchies ADD COLUMN GERENTE_TERRITORIO_NOME VARCHAR(150) DEFAULT NULL AFTER CONSULTOR_SETOR_TIPO"
);

ensureColumn(
    $connection,
    'seller_hierarchies',
    'GERENTE_TERRITORIO_CPF',
    "ALTER TABLE seller_hierarchies ADD COLUMN GERENTE_TERRITORIO_CPF VARCHAR(14) DEFAULT NULL AFTER GERENTE_TERRITORIO_NOME"
);

ensureColumn(
    $connection,
    'sales_import_queue',
    'additional_services',
    "ALTER TABLE sales_import_queue ADD COLUMN additional_services VARCHAR(255) DEFAULT NULL AFTER composition_name"
);

ensureColumn(
    $connection,
    'sales_import_queue',
    'additional_points',
    "ALTER TABLE sales_import_queue ADD COLUMN additional_points VARCHAR(255) DEFAULT NULL AFTER additional_services"
);

ensureColumn(
    $connection,
    'sales_import_queue',
    'sale_customer_type',
    "ALTER TABLE sales_import_queue ADD COLUMN sale_customer_type VARCHAR(100) DEFAULT NULL AFTER ddd"
);

ensureColumn(
    $connection,
    'sales_import_queue',
    'service_order_number',
    "ALTER TABLE sales_import_queue ADD COLUMN service_order_number VARCHAR(150) DEFAULT NULL AFTER ddd"
);

ensureColumn(
    $connection,
    'sales_import_queue',
    'cabinet_code',
    "ALTER TABLE sales_import_queue ADD COLUMN cabinet_code VARCHAR(150) DEFAULT NULL AFTER service_order_number"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_DATA_NASCIMENTO',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_DATA_NASCIMENTO DATE DEFAULT NULL AFTER CLIENTE_TIPO_DOCUMENTO"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_MAE_NOME_FANTASIA',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_MAE_NOME_FANTASIA VARCHAR(255) DEFAULT NULL AFTER CLIENTE_DATA_NASCIMENTO"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_TELEFONE_CELULAR',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_TELEFONE_CELULAR VARCHAR(20) DEFAULT NULL AFTER CLIENTE_MAE_NOME_FANTASIA"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'AUDITOR_NOME',
    "ALTER TABLE sales_nexus ADD COLUMN AUDITOR_NOME VARCHAR(150) DEFAULT NULL AFTER CLIENTE_TELEFONE_CELULAR"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'VENDA_INSTACIA',
    "ALTER TABLE sales_nexus ADD COLUMN VENDA_INSTACIA VARCHAR(150) DEFAULT NULL AFTER AUDITOR_NOME"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'VENDA_PROTOCOLO',
    "ALTER TABLE sales_nexus ADD COLUMN VENDA_PROTOCOLO VARCHAR(150) DEFAULT NULL AFTER VENDA_INSTACIA"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_LOGRADOURO_NOME',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_LOGRADOURO_NOME VARCHAR(255) DEFAULT NULL AFTER CLIENTE_CIDADE_NOME"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_LOGRADOURO_NUMERO',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_LOGRADOURO_NUMERO VARCHAR(30) DEFAULT NULL AFTER CLIENTE_LOGRADOURO_NOME"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_LOGRADOURO_COMPLEMENTO',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_LOGRADOURO_COMPLEMENTO VARCHAR(255) DEFAULT NULL AFTER CLIENTE_LOGRADOURO_NUMERO"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'CLIENTE_BAIRRO_NOME',
    "ALTER TABLE sales_nexus ADD COLUMN CLIENTE_BAIRRO_NOME VARCHAR(150) DEFAULT NULL AFTER CLIENTE_LOGRADOURO_COMPLEMENTO"
);

ensureColumn(
    $connection,
    'sales_nexus',
    'VENDA_PERIODO_INPUT',
    "ALTER TABLE sales_nexus ADD COLUMN VENDA_PERIODO_INPUT VARCHAR(6) DEFAULT NULL AFTER SGV_ADABAS_FIXA"
);

ensureColumn(
    $connection,
    'users',
    'cpf',
    "ALTER TABLE users ADD COLUMN cpf VARCHAR(11) DEFAULT NULL AFTER email"
);

ensureIndex(
    $connection,
    'users',
    'uq_users_cpf',
    "ALTER TABLE users ADD UNIQUE KEY uq_users_cpf (cpf)"
);

ensureIndex(
    $connection,
    'hierarchy_base_groups',
    'idx_hierarchy_base_groups_base_id',
    "ALTER TABLE hierarchy_base_groups ADD KEY idx_hierarchy_base_groups_base_id (base_id)"
);

ensureIndex(
    $connection,
    'users',
    'idx_users_regional_view',
    "ALTER TABLE users ADD KEY idx_users_regional_view (regional_view)"
);

ensureIndex(
    $connection,
    'users',
    'idx_users_last_seen_at',
    "ALTER TABLE users ADD KEY idx_users_last_seen_at (last_seen_at)"
);

ensureIndex(
    $connection,
    'user_base_group_scopes',
    'idx_user_base_group_scopes_base_group',
    "ALTER TABLE user_base_group_scopes ADD KEY idx_user_base_group_scopes_base_group (base_group_id)"
);

ensureIndex(
    $connection,
    'hierarchy_bases',
    'idx_hierarchy_bases_regional',
    "ALTER TABLE hierarchy_bases ADD KEY idx_hierarchy_bases_regional (REGIONAL)"
);

ensureIndex(
    $connection,
    'seller_hierarchies',
    'idx_seller_hierarchies_periodo_headcount',
    "ALTER TABLE seller_hierarchies ADD KEY idx_seller_hierarchies_periodo_headcount (PERIODO_HEADCOUNT)"
);

ensureUsersEmailNullable($connection);
ensureUsersRegionalViewEnum($connection);
ensureAuditStatusEnum($connection);
seedDefaultUserCpfs($connection);
normalizeUserRegionalViews($connection);
normalizeUserPasswordChangeFlags($connection);
normalizeSellerHierarchyHeadcountPeriods($connection);
replaceSellerHierarchyCpfUniqueIndex($connection);
applyKnownHierarchyBaseRegionalData($connection);
repairLegacyQueueData($connection);
repairSalesInputPeriod($connection);
repairMobileSalesStatuses($connection);
repairPendingSalesStatuses($connection);

fwrite(STDOUT, "Banco configurado com sucesso.\n");

function ensureColumn(PDO $connection, string $table, string $column, string $alterSql): void
{
    $tableStatement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $tableStatement->execute(['table_name' => $table]);

    if ((int) $tableStatement->fetchColumn() === 0) {
        return;
    }

    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    if ((int) $statement->fetchColumn() === 0) {
        $connection->exec($alterSql);
    }
}

function ensureIndex(PDO $connection, string $table, string $index, string $alterSql): void
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);

    if ((int) $statement->fetchColumn() === 0) {
        $connection->exec($alterSql);
    }
}

function repairLegacyQueueData(PDO $connection): void
{
    $connection->exec(
        "UPDATE sales_import_queue
         SET customer_document_type = CASE
             WHEN UPPER(customer_document_type) IN ('B2B', 'CNPJ') THEN 'B2B'
             WHEN UPPER(customer_document_type) IN ('B2C', 'CPF') THEN 'B2C'
             WHEN UPPER(customer_document_type) LIKE 'PESSOAJURI%' THEN 'B2B'
             WHEN UPPER(customer_document_type) LIKE 'PESSOA JURI%' THEN 'B2B'
             WHEN UPPER(customer_document_type) LIKE 'PESSOAFISI%' THEN 'B2C'
             WHEN UPPER(customer_document_type) LIKE 'PESSOA FISI%' THEN 'B2C'
             ELSE customer_document_type
         END
         WHERE customer_document_type IS NOT NULL
           AND customer_document_type <> ''"
    );

    $connection->exec(
        "UPDATE sales_import_queue
         SET sale_customer_type = customer_document_type
         WHERE (sale_customer_type IS NULL OR TRIM(sale_customer_type) = '')
           AND customer_document_type IN ('B2B', 'B2C')"
    );

    $connection->exec(
        "UPDATE sales_import_queue
         SET audit_status = 'AUDITANDO'
         WHERE claimed_by_user_id IS NOT NULL
           AND audit_status = 'PENDENTE INPUT'"
    );

    $connection->exec(
        "UPDATE sales_import_queue
         SET audit_status = 'PENDENTE INPUT'
         WHERE claimed_by_user_id IS NULL
           AND audit_status = 'AUDITANDO'"
    );
}

function ensureAuditStatusEnum(PDO $connection): void
{
    $connection->exec(
        "ALTER TABLE sales_import_queue
         MODIFY COLUMN audit_status ENUM('PENDENTE INPUT', 'AUDITANDO', 'FINALIZADA') NOT NULL DEFAULT 'PENDENTE INPUT'"
    );
}

function seedDefaultUserCpfs(PDO $connection): void
{
    $defaultCpfs = [
        'admin@vigg.local' => '52998224725',
        'backoffice@vigg.local' => '11144477735',
        'supervisor@vigg.local' => '12345678909',
    ];

    $statement = $connection->prepare(
        'UPDATE users
         SET cpf = :cpf
         WHERE email = :email
           AND (cpf IS NULL OR cpf = \'\')'
    );

    foreach ($defaultCpfs as $email => $cpf) {
        $statement->execute([
            'email' => $email,
            'cpf' => $cpf,
        ]);
    }
}

function normalizeUserRegionalViews(PDO $connection): void
{
    $connection->exec(
        "UPDATE users
         SET regional_view = 'FULL'
         WHERE regional_view IS NULL
            OR TRIM(regional_view) = ''
            OR regional_view NOT IN ('FULL', 'I', 'II', 'PERSONALIZADO')"
    );
}

function ensureUsersRegionalViewEnum(PDO $connection): void
{
    $connection->exec(
        "ALTER TABLE users
         MODIFY COLUMN regional_view ENUM('FULL', 'I', 'II', 'PERSONALIZADO') NOT NULL DEFAULT 'FULL'"
    );
}

function normalizeUserPasswordChangeFlags(PDO $connection): void
{
    $connection->exec(
        "UPDATE users
         SET must_change_password = 0
         WHERE must_change_password IS NULL"
    );
}

function normalizeSellerHierarchyHeadcountPeriods(PDO $connection): void
{
    $connection->exec(
        "UPDATE seller_hierarchies
         SET PERIODO_HEADCOUNT = '202603'
         WHERE PERIODO_HEADCOUNT IS NULL
            OR TRIM(PERIODO_HEADCOUNT) = ''"
    );
}

function replaceSellerHierarchyCpfUniqueIndex(PDO $connection): void
{
    $existingIndexes = $connection
        ->query("SHOW INDEX FROM seller_hierarchies WHERE Non_unique = 0")
        ->fetchAll(PDO::FETCH_ASSOC);

    $hasCompositeIndex = false;

    foreach ($existingIndexes as $index) {
        $indexName = (string) ($index['Key_name'] ?? '');

        if ($indexName === 'PRIMARY') {
            continue;
        }

        if ($indexName === 'uq_seller_hierarchies_seller_cpf_periodo') {
            $hasCompositeIndex = true;
            continue;
        }

        if ($indexName === 'uq_seller_hierarchies_seller_cpf') {
            $connection->exec('ALTER TABLE seller_hierarchies DROP INDEX uq_seller_hierarchies_seller_cpf');
        }
    }

    if (! $hasCompositeIndex) {
        $connection->exec(
            'ALTER TABLE seller_hierarchies
             ADD UNIQUE KEY uq_seller_hierarchies_seller_cpf_periodo (seller_cpf, PERIODO_HEADCOUNT)'
        );
    }
}

function applyKnownHierarchyBaseRegionalData(PDO $connection): void
{
    $knownBasesById = [
        2 => ['name' => 'VIGGO SPC PAP', 'regional' => 'I'],
        4 => ['name' => 'VIGGO SPC CO', 'regional' => 'II'],
        5 => ['name' => 'VIGGO OSASCO II', 'regional' => 'I'],
        6 => ['name' => 'VIGGO METROPOLITANO', 'regional' => 'I'],
        7 => ['name' => 'VIGGO LITORAL 13', 'regional' => 'II'],
        8 => ['name' => 'VIGGO LITORAL 12', 'regional' => 'I'],
        9 => ['name' => 'VIGGO CARRAO', 'regional' => 'I'],
        10 => ['name' => 'VIGGO BARRA FUNDA II', 'regional' => 'II'],
        11 => ['name' => 'CANAIS DESENVOLVIDOS', 'regional' => 'I'],
        12 => ['name' => 'CANAIS CONECTADOS', 'regional' => 'I'],
        18 => ['name' => 'VIGGO CANAIS CONECTADOS ADM', 'regional' => 'I'],
    ];

    $updateRegional = $connection->prepare(
        'UPDATE hierarchy_bases
         SET name = :name,
             REGIONAL = :regional
         WHERE id = :id'
    );

    foreach ($knownBasesById as $id => $baseData) {
        $updateRegional->execute([
            'id' => $id,
            'name' => $baseData['name'],
            'regional' => $baseData['regional'],
        ]);
    }

    $duplicateBaseStatement = $connection->prepare(
        'SELECT id
         FROM hierarchy_bases
         WHERE id = 17
         LIMIT 1'
    );
    $duplicateBaseStatement->execute();
    $duplicateBaseId = $duplicateBaseStatement->fetchColumn();

    $primaryBaseStatement = $connection->prepare(
        'SELECT id
         FROM hierarchy_bases
         WHERE id = 4
         LIMIT 1'
    );
    $primaryBaseStatement->execute();
    $primaryBaseId = $primaryBaseStatement->fetchColumn();

    if ($duplicateBaseId !== false && $primaryBaseId !== false) {
        $connection->prepare(
            'UPDATE hierarchy_base_groups
             SET base_id = 4
             WHERE base_id = 17'
        )->execute();

        $connection->prepare(
            'UPDATE seller_hierarchies
             SET base_id = 4
             WHERE base_id = 17'
        )->execute();

        $connection->prepare(
            'DELETE FROM hierarchy_bases
             WHERE id = 17'
        )->execute();
    }
}

function ensureUsersEmailNullable(PDO $connection): void
{
    $connection->exec(
        "ALTER TABLE users
         MODIFY COLUMN email VARCHAR(190) NULL DEFAULT NULL"
    );
}

function repairSalesInputPeriod(PDO $connection): void
{
    if (! tableExists($connection, 'sales_nexus') || ! columnExists($connection, 'sales_nexus', 'VENDA_PERIODO_INPUT')) {
        return;
    }

    $connection->exec(
        "UPDATE sales_nexus
         SET VENDA_PERIODO_INPUT = DATE_FORMAT(VENDA_DATA_INPUT, '%Y%m')
         WHERE VENDA_DATA_INPUT IS NOT NULL
           AND (VENDA_PERIODO_INPUT IS NULL OR VENDA_PERIODO_INPUT = '')"
    );
}

function repairMobileSalesStatuses(PDO $connection): void
{
    if (! tableExists($connection, 'sales_nexus')) {
        return;
    }

    $connection->exec(
        "UPDATE sales_nexus
         SET POSVENDA_STATUS = 'Movel',
             POSVENDA_SUB_STATUS = 'Movel'
         WHERE UPPER(TRIM(COALESCE(PRODUTO_CATEGORIA, ''))) = 'MOVEL'
           AND (
                TRIM(COALESCE(POSVENDA_STATUS, '')) <> 'Movel'
             OR TRIM(COALESCE(POSVENDA_SUB_STATUS, '')) <> 'Movel'
           )"
    );
}

function repairPendingSalesStatuses(PDO $connection): void
{
    if (! tableExists($connection, 'sales_nexus')) {
        return;
    }

    $connection->exec(
        "UPDATE sales_nexus
         SET POSVENDA_STATUS = 'Pendente'
         WHERE TRIM(COALESCE(POSVENDA_STATUS, '')) = 'PENDENTE'"
    );

    $connection->exec(
        "UPDATE sales_nexus
         SET POSVENDA_SUB_STATUS = 'Pendente'
         WHERE TRIM(COALESCE(POSVENDA_SUB_STATUS, '')) = 'PENDENTE'"
    );
}

function recreateLegacyCatalogProductsTable(PDO $connection): void
{
    if (! tableExists($connection, 'catalog_products')) {
        return;
    }

    if (columnExists($connection, 'catalog_products', 'PRODUTO_NOME') && columnExists($connection, 'catalog_products', 'ativo')) {
        return;
    }

    $connection->exec('DROP TABLE IF EXISTS catalog_products');
    $connection->exec(
        'CREATE TABLE catalog_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `PRODUTO_NOME` VARCHAR(255) NOT NULL,
            `PRODUTO_VALOR_GERENCIAL` DECIMAL(10,2) DEFAULT NULL,
            `PRODUTO_PONTUACAO_COMERCIAL` DECIMAL(10,2) DEFAULT NULL,
            `FATOR` DECIMAL(10,2) DEFAULT NULL,
            `CATEGORIA` VARCHAR(50) DEFAULT NULL,
            `TIPO` VARCHAR(50) DEFAULT NULL,
            `VIVO_TOTAL` VARCHAR(50) DEFAULT NULL,
            `TIPO_DOC` VARCHAR(20) DEFAULT NULL,
            `SOLO` VARCHAR(50) DEFAULT NULL,
            `2P` VARCHAR(50) DEFAULT NULL,
            `3P` VARCHAR(50) DEFAULT NULL,
            `DUO` VARCHAR(50) DEFAULT NULL,
            `PERIODO` VARCHAR(20) DEFAULT NULL,
            `ativo` TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_catalog_products_name_doc_period (`PRODUTO_NOME`, `TIPO_DOC`, `PERIODO`),
            KEY idx_catalog_products_name (`PRODUTO_NOME`),
            KEY idx_catalog_products_tipo (`TIPO`),
            KEY idx_catalog_products_categoria (`CATEGORIA`),
            KEY idx_catalog_products_ativo (`ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
}

function tableExists(PDO $connection, string $table): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);

    return (int) $statement->fetchColumn() > 0;
}

function columnExists(PDO $connection, string $table, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}
