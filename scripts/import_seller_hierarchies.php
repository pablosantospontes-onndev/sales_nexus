<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Services\HierarchyImportService;

$filePath = $argv[1] ?? '';

if ($filePath === '') {
    fwrite(STDERR, "Uso: php scripts/import_seller_hierarchies.php <caminho-do-xlsx>\n");
    exit(1);
}

if (! is_file($filePath)) {
    fwrite(STDERR, "Arquivo nao encontrado: {$filePath}\n");
    exit(1);
}

try {
    $stats = (new HierarchyImportService())->importFromXlsx($filePath, resolveAuditUserId(Database::connection()));

    fwrite(STDOUT, 'Linhas processadas: ' . $stats['processed'] . PHP_EOL);
    fwrite(STDOUT, 'Registros inseridos: ' . $stats['inserted'] . PHP_EOL);
    fwrite(STDOUT, 'Registros atualizados: ' . $stats['updated'] . PHP_EOL);
    fwrite(STDOUT, 'Operacoes criadas automaticamente: ' . $stats['created_bases'] . PHP_EOL);
    fwrite(STDOUT, 'Base grupos criados automaticamente: ' . $stats['created_groups'] . PHP_EOL);
    fwrite(STDOUT, 'Chaves duplicadas tratadas pela ultima ocorrencia: ' . $stats['duplicate_keys'] . PHP_EOL);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function resolveAuditUserId(PDO $connection): int
{
    $userId = (int) $connection
        ->query("SELECT id FROM users WHERE role = 'ADMINISTRADOR' ORDER BY id ASC LIMIT 1")
        ->fetchColumn();

    return $userId > 0 ? $userId : 0;
}
