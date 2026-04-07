<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PDO;
use RuntimeException;
use ZipArchive;

final class HierarchyImportService
{
    private const EXPECTED_HEADERS = [
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
    ];

    public function importFromXlsx(string $filePath, int $userId): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('Arquivo de hierarquia não encontrado para importação.');
        }

        $rows = $this->readSpreadsheet($filePath);

        if ($rows === []) {
            throw new RuntimeException('A planilha não possui linhas válidas para importar.');
        }

        $headers = array_keys($rows[0]);

        if ($headers !== self::EXPECTED_HEADERS) {
            throw new RuntimeException(
                'Os cabeçalhos da planilha não correspondem ao esperado. Recebido: ' . implode(', ', $headers)
            );
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $baseIdByName = $this->fetchBaseIdByName($connection);
            $groupMapByName = $this->fetchGroupMapByName($connection);
            $existingSellerMap = $this->fetchExistingSellerMap($connection);
            $preparedRowsByKey = [];
            $duplicateKeys = [];
            $createdBases = 0;
            $createdGroups = 0;

            foreach ($rows as $row) {
                $sellerCpf = $this->normalizeCpfValue($row['CONSULTOR_CPF'] ?? '');
                $periodHeadcount = $this->normalizePeriodValue($row['PERIODO_HEADCOUNT'] ?? '');
                $compositeKey = $sellerCpf . '|' . $periodHeadcount;

                if (isset($preparedRowsByKey[$compositeKey])) {
                    $duplicateKeys[$compositeKey] = ($duplicateKeys[$compositeKey] ?? 1) + 1;
                }

                $baseName = $this->requiredHierarchyText($row, 'CONSULTOR_BASE_NOME');
                $groupName = $this->normalizeHierarchyText($row['CONSULTOR_BASE_GRUPO'] ?? '') ?? $baseName;

                if (! isset($baseIdByName[$baseName])) {
                    $statement = $connection->prepare('INSERT INTO hierarchy_bases (name) VALUES (:name)');
                    $statement->execute(['name' => $baseName]);
                    $baseIdByName[$baseName] = (int) $connection->lastInsertId();
                    $createdBases++;
                }

                if (! isset($groupMapByName[$groupName])) {
                    $statement = $connection->prepare(
                        'INSERT INTO hierarchy_base_groups (name, base_id) VALUES (:name, :base_id)'
                    );
                    $statement->execute([
                        'name' => $groupName,
                        'base_id' => $baseIdByName[$baseName],
                    ]);
                    $groupMapByName[$groupName] = [
                        'id' => (int) $connection->lastInsertId(),
                        'base_id' => $baseIdByName[$baseName],
                    ];
                    $createdGroups++;
                } else {
                    $groupBaseId = (int) ($groupMapByName[$groupName]['base_id'] ?? 0);

                    if ($groupBaseId === 0) {
                        $connection->prepare(
                            'UPDATE hierarchy_base_groups
                             SET base_id = :base_id
                             WHERE id = :id'
                        )->execute([
                            'base_id' => $baseIdByName[$baseName],
                            'id' => $groupMapByName[$groupName]['id'],
                        ]);
                        $groupMapByName[$groupName]['base_id'] = $baseIdByName[$baseName];
                    } elseif ($groupBaseId !== $baseIdByName[$baseName]) {
                        throw new RuntimeException(
                            'Conflito de vinculacao para o base grupo ' . $groupName . ' entre operacoes diferentes.'
                        );
                    }
                }

                $preparedRowsByKey[$compositeKey] = [
                    'seller_name' => $this->requiredHierarchyText($row, 'CONSULTOR_NOME'),
                    'seller_cpf' => $sellerCpf,
                    'PERIODO_HEADCOUNT' => $periodHeadcount,
                    'role' => $this->requiredRole($row['CONSULTOR_TIPO'] ?? ''),
                    'supervisor_name' => $this->requiredHierarchyTextOrPlaceholder($row, 'SUPERVISOR_NOME'),
                    'supervisor_cpf' => $this->normalizeNullableCpfValue($row['SUPERVISOR_CPF'] ?? ''),
                    'coordinator_name' => $this->requiredHierarchyTextOrPlaceholder($row, 'COORDENADOR_NOME'),
                    'coordinator_cpf' => $this->normalizeNullableCpfValue($row['COORDENADOR_CPF'] ?? ''),
                    'manager_name' => $this->requiredHierarchyTextOrPlaceholder($row, 'GERENTE_BASE_NOME'),
                    'manager_cpf' => $this->normalizeNullableCpfValue($row['GERENTE_BASE_CPF'] ?? ''),
                    'base_id' => $baseIdByName[$baseName],
                    'base_group_id' => $groupMapByName[$groupName]['id'],
                    'consultant_base_regional' => $this->normalizeHierarchyText($row['CONSULTOR_BASE_REGIONAL'] ?? ''),
                    'consultant_sector_name' => $this->normalizeHierarchyText($row['CONSULTOR_SETOR_NOME'] ?? ''),
                    'consultant_sector_type' => $this->normalizeHierarchyText($row['CONSULTOR_SETOR_TIPO'] ?? ''),
                    'territory_manager_name' => $this->normalizeHierarchyText($row['GERENTE_TERRITORIO_NOME'] ?? ''),
                    'territory_manager_cpf' => $this->normalizeNullableCpfValue($row['GERENTE_TERRITORIO_CPF'] ?? ''),
                    'created_by_user_id' => $userId > 0 ? $userId : null,
                    'updated_by_user_id' => $userId > 0 ? $userId : null,
                ];
            }

            $inserted = 0;
            $updated = 0;
            $insertStatement = $connection->prepare(
                'INSERT INTO seller_hierarchies (
                    seller_name,
                    seller_cpf,
                    PERIODO_HEADCOUNT,
                    role,
                    supervisor_name,
                    SUPERVISOR_CPF,
                    coordinator_name,
                    COORDENADOR_CPF,
                    manager_name,
                    GERENTE_BASE_CPF,
                    base_id,
                    base_group_id,
                    CONSULTOR_BASE_REGIONAL,
                    CONSULTOR_SETOR_NOME,
                    CONSULTOR_SETOR_TIPO,
                    GERENTE_TERRITORIO_NOME,
                    GERENTE_TERRITORIO_CPF,
                    created_by_user_id,
                    updated_by_user_id
                ) VALUES (
                    :seller_name,
                    :seller_cpf,
                    :PERIODO_HEADCOUNT,
                    :role,
                    :supervisor_name,
                    :supervisor_cpf,
                    :coordinator_name,
                    :coordinator_cpf,
                    :manager_name,
                    :manager_cpf,
                    :base_id,
                    :base_group_id,
                    :consultant_base_regional,
                    :consultant_sector_name,
                    :consultant_sector_type,
                    :territory_manager_name,
                    :territory_manager_cpf,
                    :created_by_user_id,
                    :updated_by_user_id
                )'
            );
            $updateStatement = $connection->prepare(
                'UPDATE seller_hierarchies
                 SET seller_name = :seller_name,
                     seller_cpf = :seller_cpf,
                     PERIODO_HEADCOUNT = :PERIODO_HEADCOUNT,
                     role = :role,
                     supervisor_name = :supervisor_name,
                     SUPERVISOR_CPF = :supervisor_cpf,
                     coordinator_name = :coordinator_name,
                     COORDENADOR_CPF = :coordinator_cpf,
                     manager_name = :manager_name,
                     GERENTE_BASE_CPF = :manager_cpf,
                     base_id = :base_id,
                     base_group_id = :base_group_id,
                     CONSULTOR_BASE_REGIONAL = :consultant_base_regional,
                     CONSULTOR_SETOR_NOME = :consultant_sector_name,
                     CONSULTOR_SETOR_TIPO = :consultant_sector_type,
                     GERENTE_TERRITORIO_NOME = :territory_manager_name,
                     GERENTE_TERRITORIO_CPF = :territory_manager_cpf,
                     updated_by_user_id = :updated_by_user_id
                 WHERE id = :id'
            );

            foreach ($preparedRowsByKey as $compositeKey => $sellerRow) {
                $existingId = $existingSellerMap[$compositeKey] ?? null;

                if ($existingId !== null) {
                    $updateStatement->execute($sellerRow + ['id' => $existingId]);
                    $updated++;
                    continue;
                }

                $insertStatement->execute($sellerRow);
                $inserted++;
            }

            $connection->commit();

            return [
                'processed' => count($preparedRowsByKey),
                'inserted' => $inserted,
                'updated' => $updated,
                'created_bases' => $createdBases,
                'created_groups' => $createdGroups,
                'duplicate_keys' => count($duplicateKeys),
            ];
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

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
        return trim(preg_replace('/\s+/', ' ', normalize_text($value)) ?? '');
    }

    private function requiredHierarchyText(array $row, string $column): string
    {
        $value = $this->normalizeHierarchyText($row[$column] ?? '');

        if ($value === null) {
            throw new RuntimeException('Campo obrigatorio ausente na planilha: ' . $column);
        }

        return $value;
    }

    private function requiredHierarchyTextOrPlaceholder(array $row, string $column): string
    {
        return $this->normalizeHierarchyText($row[$column] ?? '') ?? '-';
    }

    private function normalizeHierarchyText(?string $value): ?string
    {
        $normalized = normalize_text($value);

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return mb_strtoupper($normalized, 'UTF-8');
    }

    private function requiredRole(?string $value): string
    {
        $role = $this->normalizeHierarchyText($value);
        $allowed = ['CONSULTOR', 'SUPERVISOR', 'COORDENADOR', 'GERENTE'];

        if ($role === null || ! in_array($role, $allowed, true)) {
            throw new RuntimeException('Tipo hierárquico inválido na planilha: ' . normalize_text($value));
        }

        return $role;
    }

    private function normalizeCpfValue(?string $value): string
    {
        $cpf = clean_document($value);

        if ($cpf === '') {
            throw new RuntimeException('Encontrado vendedor sem CPF válido na planilha.');
        }

        if (strlen($cpf) !== 11 || ! valid_cpf($cpf)) {
            throw new RuntimeException('CPF inválido encontrado na planilha: ' . $value);
        }

        return $cpf;
    }

    private function normalizeNullableCpfValue(?string $value): ?string
    {
        $cpf = clean_document($value);

        if ($cpf === '') {
            return null;
        }

        if (strlen($cpf) !== 11 || ! valid_cpf($cpf)) {
            throw new RuntimeException('CPF inválido encontrado na planilha: ' . $value);
        }

        return $cpf;
    }

    private function normalizePeriodValue(?string $value): string
    {
        $period = clean_document($value);

        if (! preg_match('/^\d{6}$/', $period)) {
            throw new RuntimeException('Período headcount inválido na planilha: ' . normalize_text($value));
        }

        $month = (int) substr($period, 4, 2);

        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Período headcount inválido na planilha: ' . normalize_text($value));
        }

        return $period;
    }

    private function fetchBaseIdByName(PDO $connection): array
    {
        $statement = $connection->query('SELECT id, name FROM hierarchy_bases ORDER BY id ASC');
        $map = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['name']] = (int) $row['id'];
        }

        return $map;
    }

    private function fetchGroupMapByName(PDO $connection): array
    {
        $statement = $connection->query('SELECT id, name, base_id FROM hierarchy_base_groups ORDER BY id ASC');
        $map = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['name']] = [
                'id' => (int) $row['id'],
                'base_id' => (int) ($row['base_id'] ?? 0),
            ];
        }

        return $map;
    }

    private function fetchExistingSellerMap(PDO $connection): array
    {
        $statement = $connection->query('SELECT id, seller_cpf, PERIODO_HEADCOUNT FROM seller_hierarchies');
        $map = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['seller_cpf'] . '|' . (string) $row['PERIODO_HEADCOUNT']] = (int) $row['id'];
        }

        return $map;
    }
}
