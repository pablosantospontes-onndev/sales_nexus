<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class ProductCatalogImportService
{
    public function importFromXlsx(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('Arquivo de produtos não encontrado para importação.');
        }

        $rows = $this->readSpreadsheet($filePath);

        if ($rows === []) {
            throw new RuntimeException('A planilha não possui linhas válidas para importar.');
        }

        $headers = array_keys($rows[0]);

        if ($headers !== ProductCatalogExportService::HEADERS) {
            throw new RuntimeException(
                'Os cabeçalhos da planilha não correspondem ao layout exportado pelo sistema.'
            );
        }

        return (new ProductRepository())->importRows($rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSpreadsheet(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX informada.');
        }

        try {
            $sharedStrings = $this->parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if (! is_string($sheetXml) || $sheetXml === '') {
                throw new RuntimeException('Não foi possível localizar a primeira planilha dentro do XLSX.');
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

                    $indexedValues[$this->columnLettersToIndex($columnLetters)] = $this->cellValue(
                        $cellNode,
                        $xpath,
                        $sharedStrings
                    );
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

    /**
     * @return array<int, string>
     */
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

            $parts = [];

            foreach ($xpath->query('.//main:t', $stringItem) as $textNode) {
                $parts[] = $textNode->textContent;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellValue(DOMElement $cellNode, DOMXPath $xpath, array $sharedStrings): string
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

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($position = 0; $position < strlen($letters); $position++) {
            $index = ($index * 26) + (ord($letters[$position]) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
