<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class ReportsExportService
{
    private const CURRENCY_COLUMNS = [
        'PRODUTO_VALOR_GERENCIAL',
        'PRODUTO_PONTUACAO_COMERCIAL',
    ];

    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $rows
     */
    public function download(array $headers, array $rows): never
    {
        $temporaryFile = $this->buildSpreadsheetFile($headers, $rows);
        $filename = 'relatorio_sales_nexus_' . date('Ymd_His') . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($temporaryFile));
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: public');

        readfile($temporaryFile);
        @unlink($temporaryFile);
        exit;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildSpreadsheetFile(array $headers, array $rows): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'sales_nexus_report_');

        if ($temporaryFile === false) {
            throw new RuntimeException('Não foi possível gerar o arquivo temporário do relatório.');
        }

        $zip = new ZipArchive();

        if ($zip->open($temporaryFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryFile);
            throw new RuntimeException('Não foi possível montar o arquivo XLSX do relatório.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        $zip->close();

        return $temporaryFile;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $rows
     */
    private function worksheetXml(array $headers, array $rows): string
    {
        $xmlRows = [];

        $headerCells = [];

        foreach ($headers as $columnIndex => $header) {
            $cellReference = $this->columnIndexToLetters($columnIndex) . '1';
            $headerCells[] = sprintf(
                '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                $cellReference,
                $this->xmlText($header)
            );
        }

        $xmlRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $cells = [];

            foreach ($headers as $columnIndex => $header) {
                $cellReference = $this->columnIndexToLetters($columnIndex) . $excelRow;
                $value = $row[$header] ?? null;

                if ($this->isCurrencyColumn($header) && is_numeric($value)) {
                    $cells[] = sprintf(
                        '<c r="%s" s="1"><v>%s</v></c>',
                        $cellReference,
                        $this->xmlText(number_format((float) $value, 2, '.', ''))
                    );
                    continue;
                }

                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $cellReference,
                    $this->xmlText($this->stringifyValue($value))
                );
            }

            $xmlRows[] = sprintf('<row r="%d">%s</row>', $excelRow, implode('', $cells));
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '</worksheet>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Relatorios" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="_-[$R$-416]* #,##0.00_-;_-[$R$-416]* -#,##0.00_-;_-[$R$-416]* &quot;-&quot;??_-;_-@_-"/></numFmts>'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" numFmtId="164" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function isCurrencyColumn(string $header): bool
    {
        return in_array(strtoupper($header), self::CURRENCY_COLUMNS, true);
    }

    private function columnIndexToLetters(int $columnIndex): string
    {
        $index = $columnIndex + 1;
        $letters = '';

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = (int) floor(($index - 1) / 26);
        }

        return $letters;
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
