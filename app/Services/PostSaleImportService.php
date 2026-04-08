<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class PostSaleImportService
{
    private const REQUIRED_HEADERS = [
        'Número Ordem',
        'Etapa Instalação',
        'Etapa Status Instalação',
        'Data Instalação/Cancelamento',
    ];

    private const STATUS_MAPPING_FILE = APP_ROOT . '/config/post_sales_mappings/posvenda_status.xlsx';
    private const SUB_STATUS_MAPPING_FILE = APP_ROOT . '/config/post_sales_mappings/posvenda_sub_status.xlsx';

    public function importFromXlsx(array $file): array
    {
        $filePath = $this->moveUploadedFile($file);
        $rows = $this->readSpreadsheet($filePath);

        if ($rows === []) {
            throw new RuntimeException('A planilha não possui linhas válidas para importar.');
        }

        $headers = array_keys($rows[0]);
        $resolvedHeaders = [];

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            $resolvedHeaders[$requiredHeader] = $this->resolveHeader($headers, $requiredHeader);
        }

        $statusMap = $this->loadMapping(self::STATUS_MAPPING_FILE, 'Etapa Instalação', 'POSVENDA_STATUS');
        $subStatusMap = $this->loadMapping(self::SUB_STATUS_MAPPING_FILE, 'Etapa Status Instalação', 'POSVENDA_SUB_STATUS');

        $connection = Database::connection();
        $connection->beginTransaction();

        $updateStatement = $connection->prepare(
            'UPDATE sales_nexus
             SET POSVENDA_STATUS = :status,
                 POSVENDA_SUB_STATUS = :sub_status,
                 POSVENDA_DATA_INSTALACAO = :installation_date
             WHERE VENDA_ORDEM_DE_SERVICO = :service_order'
        );

        $existsStatement = $connection->prepare(
            'SELECT COUNT(*) FROM sales_nexus WHERE VENDA_ORDEM_DE_SERVICO = :service_order'
        );

        $totalRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $notFoundRows = 0;

        try {
            foreach ($rows as $row) {
                $totalRows++;
                $serviceOrder = trim((string) ($row[$resolvedHeaders['Número Ordem']] ?? ''));

                if ($serviceOrder === '') {
                    $skippedRows++;
                    continue;
                }

                $existsStatement->execute(['service_order' => $serviceOrder]);
                $existingCount = (int) $existsStatement->fetchColumn();

                if ($existingCount === 0) {
                    $notFoundRows++;
                    continue;
                }

                $statusRaw = trim((string) ($row[$resolvedHeaders['Etapa Instalação']] ?? ''));
                $subStatusRaw = trim((string) ($row[$resolvedHeaders['Etapa Status Instalação']] ?? ''));
                $dateRaw = (string) ($row[$resolvedHeaders['Data Instalação/Cancelamento']] ?? '');

                $status = $this->mapValue($statusRaw, $statusMap);
                $subStatus = $this->mapValue($subStatusRaw, $subStatusMap);
                $installationDate = $this->normalizeDateValue($dateRaw);

                $updateStatement->execute([
                    'status' => $status !== '' ? $status : null,
                    'sub_status' => $subStatus !== '' ? $subStatus : null,
                    'installation_date' => $installationDate,
                    'service_order' => $serviceOrder,
                ]);

                $updatedRows += $existingCount;
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }

        return [
            'total_rows' => $totalRows,
            'updated_rows' => $updatedRows,
            'skipped_rows' => $skippedRows,
            'not_found_rows' => $notFoundRows,
            'message' => 'Atualização concluída com sucesso.',
        ];
    }

    private function moveUploadedFile(array $file): string
    {
        if (! isset($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Arquivo XLSX inválido para importação.');
        }

        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao enviar o arquivo XLSX.');
        }

        $storagePath = APP_ROOT . '/storage/post_sales';
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($file['name'] ?? 'pos_venda.xlsx'));
        $targetPath = $storagePath . '/' . date('Ymd_His') . '_' . $safeName;

        if (! move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Não foi possível salvar o arquivo XLSX enviado.');
        }

        return $targetPath;
    }

    private function loadMapping(string $filePath, string $sourceHeader, string $targetHeader): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('Arquivo de parametrização não encontrado: ' . $filePath);
        }

        $rows = $this->readSpreadsheet($filePath);
        if ($rows === []) {
            return [];
        }

        $mapping = [];

        foreach ($rows as $row) {
            $source = trim((string) ($row[$sourceHeader] ?? ''));
            $target = trim((string) ($row[$targetHeader] ?? ''));
            if ($source === '' || $target === '') {
                continue;
            }

            $mapping[$this->normalizeKey($source)] = $target;
        }

        return $mapping;
    }

    private function mapValue(string $value, array $mapping): string
    {
        if ($value === '') {
            return '';
        }

        $key = $this->normalizeKey($value);

        return $mapping[$key] ?? $value;
    }

    private function resolveHeader(array $headers, string $required): string
    {
        if (in_array($required, $headers, true)) {
            return $required;
        }

        $normalizedMap = [];
        foreach ($headers as $header) {
            $normalizedKey = normalize_text($header);
            if ($normalizedKey !== '') {
                $normalizedMap[$normalizedKey] = $header;
            }
        }

        $requiredKey = normalize_text($required);
        if ($requiredKey !== '' && isset($normalizedMap[$requiredKey])) {
            return $normalizedMap[$requiredKey];
        }

        throw new RuntimeException('Cabeçalho obrigatório ausente: ' . $required);
    }

    private function normalizeKey(string $value): string
    {
        return strtoupper(normalize_text(trim($value)));
    }

    private function normalizeDateValue(string $value): ?string
    {
        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $base = new \DateTimeImmutable('1899-12-30');
            $days = (int) round((float) $raw);
            if ($days <= 0) {
                return null;
            }
            return $base->modify('+' . $days . ' days')->format('Y-m-d');
        }

        if (preg_match('/^(\d{2})[\/.-](\d{2})[\/.-](\d{4})$/', $raw, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/^(\d{4})[\/.-](\d{2})[\/.-](\d{2})$/', $raw, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function readSpreadsheet(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX informada.');
        }

        try {
            $sharedStrings = $this->parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
            $rows = [];

            foreach ($this->listWorksheetPaths($zip) as $sheetPath) {
                $sheetXml = $zip->getFromName($sheetPath);

                if (! is_string($sheetXml) || $sheetXml === '') {
                    continue;
                }

                $rows = $this->extractRowsFromSheet($sheetXml, $sharedStrings);

                if ($rows !== []) {
                    break;
                }
            }

            if ($rows === []) {
                return [];
            }

            $headers = array_values(array_map([$this, 'normalizeHeader'], $rows[0]));
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

    private function parseSharedStrings(string $xml): array
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

    private function normalizeHeader(string $header): string
    {
        return trim($header);
    }

    private function cellValue(DOMElement $cell, DOMXPath $xpath, array $sharedStrings): string
    {
        $valueNode = $xpath->query('main:v', $cell)->item(0);

        if (! $valueNode instanceof DOMElement) {
            $inlineNode = $xpath->query('main:is/main:t', $cell)->item(0);
            if ($inlineNode instanceof DOMElement) {
                return $inlineNode->textContent ?? '';
            }
            return '';
        }

        $value = $valueNode->textContent ?? '';
        $type = $cell->getAttribute('t');

        if ($type === 's') {
            $index = (int) $value;
            return $sharedStrings[$index] ?? '';
        }

        return $value;
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $length = strlen($letters);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * @return array<int, string>
     */
    private function listWorksheetPaths(ZipArchive $zip): array
    {
        $fallback = ['xl/worksheets/sheet1.xml'];
        $workbookXml = $zip->getFromName('xl/workbook.xml');

        if (! is_string($workbookXml) || $workbookXml === '') {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $dom = new DOMDocument();
        $dom->loadXML($workbookXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheetNode = $xpath->query('/main:workbook/main:sheets/main:sheet')->item(0);
        if (! $sheetNode instanceof DOMElement) {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $relId = $sheetNode->getAttribute('r:id');
        if ($relId === '') {
            $relId = $sheetNode->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        }

        if ($relId === '') {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! is_string($relsXml) || $relsXml === '') {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $relsDom = new DOMDocument();
        $relsDom->loadXML($relsXml);
        $relsXpath = new DOMXPath($relsDom);
        $relsXpath->registerNamespace('main', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relNode = $relsXpath->query('/main:Relationships/main:Relationship[@Id="' . $relId . '"]')->item(0);

        if (! $relNode instanceof DOMElement) {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $target = (string) $relNode->getAttribute('Target');
        if ($target === '') {
            return $this->fallbackWorksheetPaths($zip, $fallback);
        }

        $targetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . ltrim($target, '/');
        $ordered = $zip->getFromName($targetPath) !== false ? [$targetPath] : [];

        return $this->fallbackWorksheetPaths($zip, $ordered !== [] ? $ordered : $fallback);
    }

    private function fallbackWorksheetPaths(ZipArchive $zip, array $defaults): array
    {
        $paths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $paths[] = $name;
            }
        }

        if ($paths === []) {
            return $defaults;
        }

        sort($paths);

        $ordered = array_values(array_unique(array_merge($defaults, $paths)));

        return $ordered;
    }

    private function extractRowsFromSheet(string $sheetXml, array $sharedStrings): array
    {
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
            $fallbackIndex = 0;

            foreach ($xpath->query('main:c', $rowNode) as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $reference = $cellNode->getAttribute('r');
                $columnLetters = preg_replace('/\d+/', '', $reference) ?? '';

                if ($columnLetters !== '') {
                    $columnIndex = $this->columnLettersToIndex($columnLetters);
                } else {
                    $columnIndex = $fallbackIndex;
                }

                $indexedValues[$columnIndex] = $this->cellValue(
                    $cellNode,
                    $xpath,
                    $sharedStrings
                );

                $fallbackIndex++;
            }

            if ($indexedValues === []) {
                continue;
            }

            ksort($indexedValues);
            $rows[] = $indexedValues;
        }

        return $rows;
    }
}
