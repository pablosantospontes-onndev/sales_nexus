<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$filePath = $argv[1] ?? '';

if ($filePath === '') {
    fwrite(STDERR, "Uso: php scripts/import_catalog_products.php <caminho-do-xlsx>\n");
    exit(1);
}

if (! is_file($filePath)) {
    fwrite(STDERR, "Arquivo nao encontrado: {$filePath}\n");
    exit(1);
}

$expectedHeaders = [
    'PRODUTO_NOME',
    'PRODUTO_VALOR_GERENCIAL',
    'PRODUTO_PONTUACAO_COMERCIAL',
    'FATOR',
    'CATEGORIA',
    'TIPO',
    'VIVO_TOTAL',
    'TIPO_DOC',
    'SOLO',
    '2P',
    '3P',
    'DUO',
    'PERIODO',
];

try {
    $rows = readCatalogSpreadsheet($filePath);

    if ($rows === []) {
        throw new RuntimeException('A planilha nao possui linhas de produtos para importar.');
    }

    $headers = array_keys($rows[0]);

    if ($headers !== $expectedHeaders) {
        throw new RuntimeException(
            'Os cabecalhos da planilha nao correspondem ao esperado. Recebido: ' . implode(', ', $headers)
        );
    }

    $connection = Database::connection();
    $connection->beginTransaction();
    $connection->exec('DELETE FROM catalog_products');

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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            1
        )'
    );

    foreach ($rows as $row) {
        $statement->execute([
            normalizeCatalogText($row['PRODUTO_NOME']),
            normalizeCatalogDecimal($row['PRODUTO_VALOR_GERENCIAL']),
            normalizeCatalogDecimal($row['PRODUTO_PONTUACAO_COMERCIAL']),
            normalizeCatalogDecimal($row['FATOR']),
            normalizeCatalogText($row['CATEGORIA']),
            normalizeCatalogText($row['TIPO']),
            normalizeCatalogText($row['VIVO_TOTAL']),
            normalizeCatalogText($row['TIPO_DOC']),
            normalizeCatalogText($row['SOLO']),
            normalizeCatalogText($row['2P']),
            normalizeCatalogText($row['3P']),
            normalizeCatalogText($row['DUO']),
            normalizeCatalogText($row['PERIODO']),
        ]);
    }

    $connection->commit();
    $connection->exec('ALTER TABLE catalog_products AUTO_INCREMENT = 1');

    fwrite(STDOUT, 'Produtos importados com sucesso: ' . count($rows) . PHP_EOL);
} catch (Throwable $throwable) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function readCatalogSpreadsheet(string $filePath): array
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

        $headers = array_values(array_map('normalizeCatalogHeader', $rows[0]));
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

function normalizeCatalogHeader(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function normalizeCatalogText(string $value): ?string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

    return $normalized === '' ? null : $normalized;
}

function normalizeCatalogDecimal(string $value): ?string
{
    $normalized = str_replace(',', '.', trim($value));

    if ($normalized === '' || $normalized === '-') {
        return null;
    }

    if (! is_numeric($normalized)) {
        throw new RuntimeException('Valor decimal invalido encontrado na planilha: ' . $value);
    }

    return number_format((float) $normalized, 2, '.', '');
}
