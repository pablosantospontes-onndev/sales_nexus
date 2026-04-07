<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$filePath = $argv[1] ?? '';

if ($filePath === '') {
    fwrite(STDERR, "Uso: php scripts/import_base_group_links.php <caminho-do-xlsx>\n");
    exit(1);
}

if (! is_file($filePath)) {
    fwrite(STDERR, "Arquivo nao encontrado: {$filePath}\n");
    exit(1);
}

try {
    $rows = readSpreadsheet($filePath);

    if ($rows === []) {
        throw new RuntimeException('A planilha nao possui linhas para importar.');
    }

    $headers = array_keys($rows[0]);
    $requiredHeaders = ['CONSULTOR_BASE_NOME', 'CONSULTOR_BASE_GRUPO'];

    foreach ($requiredHeaders as $requiredHeader) {
        if (! in_array($requiredHeader, $headers, true)) {
            throw new RuntimeException('Cabecalho obrigatorio ausente na planilha: ' . $requiredHeader);
        }
    }

    $connection = Database::connection();
    $connection->beginTransaction();

    $pairs = [];

    foreach ($rows as $row) {
        $baseName = normalizeHierarchyText($row['CONSULTOR_BASE_NOME'] ?? '');
        $groupName = normalizeHierarchyText($row['CONSULTOR_BASE_GRUPO'] ?? '');

        if ($baseName !== null && $groupName !== null) {
            $pairs[$groupName] = $baseName;
        }
    }

    $statement = $connection->query(
        'SELECT DISTINCT hierarchy_base_groups.name AS group_name, hierarchy_bases.name AS base_name
         FROM seller_hierarchies
         INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
         INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id'
    );

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $groupName = normalizeHierarchyText($row['group_name'] ?? '');
        $baseName = normalizeHierarchyText($row['base_name'] ?? '');

        if ($groupName === null || $baseName === null) {
            continue;
        }

        if (isset($pairs[$groupName]) && $pairs[$groupName] !== $baseName) {
            throw new RuntimeException(
                'Conflito encontrado para o base grupo ' . $groupName . ' entre a planilha e os vendedores atuais.'
            );
        }

        $pairs[$groupName] = $baseName;
    }

    $baseIdByName = fetchNameMap($connection, 'hierarchy_bases');
    $groupRowsByName = fetchGroupRowsByName($connection);
    $createdBases = 0;
    $createdGroups = 0;
    $updatedGroups = 0;

    foreach ($pairs as $groupName => $baseName) {
        if (! isset($baseIdByName[$baseName])) {
            $connection
                ->prepare('INSERT INTO hierarchy_bases (name) VALUES (:name)')
                ->execute(['name' => $baseName]);
            $baseIdByName[$baseName] = (int) $connection->lastInsertId();
            $createdBases++;
        }

        $baseId = $baseIdByName[$baseName];

        if (! isset($groupRowsByName[$groupName])) {
            $connection
                ->prepare('INSERT INTO hierarchy_base_groups (name, base_id) VALUES (:name, :base_id)')
                ->execute([
                    'name' => $groupName,
                    'base_id' => $baseId,
                ]);
            $groupRowsByName[$groupName] = [
                'id' => (int) $connection->lastInsertId(),
                'base_id' => $baseId,
            ];
            $createdGroups++;
            continue;
        }

        if ((int) ($groupRowsByName[$groupName]['base_id'] ?? 0) !== $baseId) {
            $connection
                ->prepare('UPDATE hierarchy_base_groups SET base_id = :base_id WHERE id = :id')
                ->execute([
                    'base_id' => $baseId,
                    'id' => $groupRowsByName[$groupName]['id'],
                ]);
            $groupRowsByName[$groupName]['base_id'] = $baseId;
            $updatedGroups++;
        }
    }

    $connection->exec(
        'UPDATE seller_hierarchies
         INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
         SET seller_hierarchies.base_id = hierarchy_base_groups.base_id
         WHERE hierarchy_base_groups.base_id IS NOT NULL
           AND seller_hierarchies.base_id <> hierarchy_base_groups.base_id'
    );

    $connection->commit();

    fwrite(STDOUT, 'Vinculos base/grupo importados com sucesso.' . PHP_EOL);
    fwrite(STDOUT, 'Operacoes criadas: ' . $createdBases . PHP_EOL);
    fwrite(STDOUT, 'Base grupos criados: ' . $createdGroups . PHP_EOL);
    fwrite(STDOUT, 'Base grupos atualizados: ' . $updatedGroups . PHP_EOL);
    fwrite(STDOUT, 'Total de vinculacoes consideradas: ' . count($pairs) . PHP_EOL);
} catch (Throwable $throwable) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function readSpreadsheet(string $filePath): array
{
    $zip = new ZipArchive();

    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Nao foi possivel abrir a planilha XLSX informada.');
    }

    try {
        $sharedStrings = parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if (! is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('Nao foi possivel localizar a primeira planilha dentro do XLSX.');
        }

        $dom = new DOMDocument();
        $dom->loadXML($sheetXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($xpath->query('/main:worksheet/main:sheetData/main:row') as $rowNode) {
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            $indexedValues = [];

            foreach ($xpath->query('main:c', $rowNode) as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $reference = $cellNode->getAttribute('r');
                $columnLetters = preg_replace('/\d+/', '', $reference) ?? '';

                if ($columnLetters === '') {
                    continue;
                }

                $indexedValues[columnLettersToIndex($columnLetters)] = cellValue($cellNode, $xpath, $sharedStrings);
            }

            if ($indexedValues === []) {
                continue;
            }

            ksort($indexedValues);
            $rows[] = $indexedValues;
        }

        if ($rows === []) {
            return [];
        }

        $headers = array_values(array_map('normalizeHeader', $rows[0]));
        $records = [];

        foreach (array_slice($rows, 1) as $rowValues) {
            $line = [];

            foreach ($headers as $index => $header) {
                $line[$header] = array_key_exists($index, $rowValues) ? trim((string) $rowValues[$index]) : '';
            }

            if (implode('', $line) === '') {
                continue;
            }

            $records[] = $line;
        }

        return $records;
    } finally {
        $zip->close();
    }
}

function parseSharedStrings(string $xml): array
{
    if ($xml === '') {
        return [];
    }

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $strings = [];

    foreach ($xpath->query('/main:sst/main:si') as $stringItem) {
        if (! $stringItem instanceof DOMElement) {
            continue;
        }

        $textParts = [];

        foreach ($xpath->query('.//main:t', $stringItem) as $textNode) {
            $textParts[] = $textNode->textContent;
        }

        $strings[] = implode('', $textParts);
    }

    return $strings;
}

function cellValue(DOMElement $cellNode, DOMXPath $xpath, array $sharedStrings): string
{
    $type = $cellNode->getAttribute('t');

    if ($type === 'inlineStr') {
        $parts = [];

        foreach ($xpath->query('.//main:t', $cellNode) as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return trim(implode('', $parts));
    }

    $valueNode = $xpath->query('main:v', $cellNode)->item(0);
    $rawValue = $valueNode instanceof DOMNode ? trim($valueNode->textContent) : '';

    if ($type === 's' && $rawValue !== '' && isset($sharedStrings[(int) $rawValue])) {
        return trim($sharedStrings[(int) $rawValue]);
    }

    return $rawValue;
}

function columnLettersToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;

    for ($position = 0; $position < strlen($letters); $position++) {
        $index = ($index * 26) + (ord($letters[$position]) - 64);
    }

    return $index - 1;
}

function normalizeHeader(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', normalize_text($value)) ?? '');
}

function normalizeHierarchyText(?string $value): ?string
{
    $normalized = normalize_text($value);

    if ($normalized === '' || $normalized === '-') {
        return null;
    }

    return mb_strtoupper($normalized, 'UTF-8');
}

function fetchNameMap(PDO $connection, string $table): array
{
    $statement = $connection->query("SELECT id, name FROM {$table} ORDER BY id ASC");
    $map = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['name']] = (int) $row['id'];
    }

    return $map;
}

function fetchGroupRowsByName(PDO $connection): array
{
    $statement = $connection->query('SELECT id, name, base_id FROM hierarchy_base_groups ORDER BY id ASC');
    $map = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['name']] = [
            'id' => (int) $row['id'],
            'base_id' => isset($row['base_id']) ? (int) $row['base_id'] : 0,
        ];
    }

    return $map;
}
