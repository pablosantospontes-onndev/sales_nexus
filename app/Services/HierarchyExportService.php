<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class HierarchyExportService
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function download(array $rows): never
    {
        $filename = 'hierarquia_' . date('Ymd_His') . '.xlsx';
        $temporaryFile = $this->buildSpreadsheetFile($rows);

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
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildSpreadsheetFile(array $rows): string
    {
        $headers = [
            'CONSULTOR_NOME',
            'CONSULTOR_CPF',
            'PERIODO_HEADCOUNT',
            'CONSULTOR_TIPO',
            'CONSULTOR_BASE_NOME',
            'CONSULTOR_BASE_GRUPO',
            'CONSULTOR_BASE_REGIONAL',
            'CONSULTOR_SETOR_NOME',
            'CONSULTOR_SETOR_TIPO',
            'SUPERVISOR_NOME',
            'SUPERVISOR_CPF',
            'COORDENADOR_NOME',
            'COORDENADOR_CPF',
            'GERENTE_BASE_NOME',
            'GERENTE_BASE_CPF',
            'GERENTE_TERRITORIO_NOME',
            'GERENTE_TERRITORIO_CPF',
            'CREATED_AT',
            'UPDATED_AT',
        ];

        $sheetRows = [$headers];

        foreach ($rows as $row) {
            $sheetRows[] = [
                (string) ($row['seller_name'] ?? ''),
                (string) ($row['seller_cpf'] ?? ''),
                (string) ($row['PERIODO_HEADCOUNT'] ?? ''),
                (string) ($row['role'] ?? ''),
                (string) ($row['base_name'] ?? ''),
                (string) ($row['base_group_name'] ?? ''),
                (string) ($row['CONSULTOR_BASE_REGIONAL'] ?? ''),
                (string) ($row['CONSULTOR_SETOR_NOME'] ?? ''),
                (string) ($row['CONSULTOR_SETOR_TIPO'] ?? ''),
                (string) ($row['supervisor_name'] ?? ''),
                (string) ($row['SUPERVISOR_CPF'] ?? ''),
                (string) ($row['coordinator_name'] ?? ''),
                (string) ($row['COORDENADOR_CPF'] ?? ''),
                (string) ($row['manager_name'] ?? ''),
                (string) ($row['GERENTE_BASE_CPF'] ?? ''),
                (string) ($row['GERENTE_TERRITORIO_NOME'] ?? ''),
                (string) ($row['GERENTE_TERRITORIO_CPF'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['updated_at'] ?? ''),
            ];
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'hierarquia_');

        if ($temporaryFile === false) {
            throw new RuntimeException('Não foi possível gerar o arquivo temporário de exportação.');
        }

        $zip = new ZipArchive();

        if ($zip->open($temporaryFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryFile);
            throw new RuntimeException('Não foi possível montar o arquivo XLSX para exportação.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($sheetRows));
        $zip->close();

        return $temporaryFile;
    }

    private function worksheetXml(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $rowValues) {
            $excelRow = $rowIndex + 1;
            $cells = [];

            foreach ($rowValues as $columnIndex => $value) {
                $cellReference = $this->columnIndexToLetters($columnIndex) . $excelRow;
                $escapedValue = $this->xmlText((string) $value);
                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $cellReference,
                    $escapedValue
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
            . '<sheets><sheet name="Hierarquia" sheetId="1" r:id="rId1"/></sheets>'
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
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
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
