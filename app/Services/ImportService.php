<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\ImportBatchRepository;
use RuntimeException;
use ZipArchive;

final class ImportService
{
    private const REQUIRED_COLUMNS = [
        'codigo da venda' => 'sale_code',
        'pdv - adabas' => 'pdv_adabas',
        'data input da venda' => 'sale_input_date',
        'horario' => 'sale_input_time',
        'tipo cliente (cpf / cnpj)' => 'customer_document_type',
        'cpf / cnpj do cliente' => 'customer_document',
        'nome do cliente' => 'customer_name',
        'cliente - razao social' => 'customer_company_name',
        'telefone celular' => 'phone_mobile',
        'telefone alternativo 1' => 'phone_alt_1',
        'telefone alternativo 2' => 'phone_alt_2',
        'e-mail do cliente' => 'customer_email',
        'cidade do cliente' => 'customer_city',
        'uf do cliente' => 'customer_uf',
        'regional do cliente' => 'customer_regional',
        'cep do cliente' => 'customer_cep',
        'tipo de servico' => 'service_type',
        'servico' => 'service_name',
        'plano' => 'plan_name',
        'pacote' => 'package_name',
        'composicao' => 'composition_name',
        'servicos adicionais' => 'additional_services',
        'pontos adicionais' => 'additional_points',
        'cpf do vendedor' => 'seller_cpf',
        'regional' => 'sale_regional',
        'ddd' => 'ddd',
        'numero ordem' => 'service_order_number',
        'codigo armario (cnl + at)' => 'cabinet_code',
        'tipo de cliente' => 'sale_customer_type',
        'status da venda' => 'sale_status',
        'status do servico' => 'service_status',
    ];

    public function importFromZip(array $uploadedFile, int $userId): array
    {
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Selecione um arquivo ZIP válido para importar.');
        }

        $originalFilename = normalize_text($uploadedFile['name'] ?? '');
        if (strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('O arquivo enviado precisa estar no formato .zip.');
        }

        $zip = new ZipArchive();
        if ($zip->open((string) $uploadedFile['tmp_name']) !== true) {
            throw new RuntimeException('Não foi possível abrir o ZIP enviado.');
        }

        $csvName = $this->locateCsvName($zip);
        if ($csvName === null) {
            $zip->close();
            throw new RuntimeException('Não encontrei o arquivo analitico_vendas_fixa.csv dentro do ZIP.');
        }

        $stream = $zip->getStream($csvName);
        if (! is_resource($stream)) {
            $zip->close();
            throw new RuntimeException('Não foi possível ler o CSV dentro do ZIP.');
        }

        $headerLine = fgets($stream);
        if ($headerLine === false) {
            fclose($stream);
            $zip->close();
            throw new RuntimeException('O CSV veio vazio.');
        }

        $delimiter = $this->detectDelimiter($headerLine);
        $headers = array_map('ascii_key', str_getcsv(normalize_text($headerLine), $delimiter));
        $headerMap = array_flip($headers);

        foreach (array_keys(self::REQUIRED_COLUMNS) as $requiredColumn) {
            if (! array_key_exists($requiredColumn, $headerMap)) {
                fclose($stream);
                $zip->close();
                throw new RuntimeException("Coluna obrigatoria ausente no CSV: {$requiredColumn}");
            }
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $batchRepository = new ImportBatchRepository();
            $batchId = $batchRepository->create($originalFilename, $csvName, $userId);

            $insert = $connection->prepare(
                'INSERT IGNORE INTO sales_import_queue (
                    import_batch_id, sale_code, pdv_adabas, sale_input_date, sale_input_time,
                    customer_document_type, customer_document, customer_name, phone_mobile,
                    phone_alt_1, phone_alt_2, customer_email, customer_city, customer_uf,
                    customer_regional, customer_cep, service_type, service_name, plan_name,
                    package_name, composition_name, additional_services, additional_points, seller_cpf, sale_regional, ddd, service_order_number, cabinet_code, sale_customer_type,
                    sale_status, service_status
                ) VALUES (
                    :import_batch_id, :sale_code, :pdv_adabas, :sale_input_date, :sale_input_time,
                    :customer_document_type, :customer_document, :customer_name, :phone_mobile,
                    :phone_alt_1, :phone_alt_2, :customer_email, :customer_city, :customer_uf,
                    :customer_regional, :customer_cep, :service_type, :service_name, :plan_name,
                    :package_name, :composition_name, :additional_services, :additional_points, :seller_cpf, :sale_regional, :ddd, :service_order_number, :cabinet_code, :sale_customer_type,
                    :sale_status, :service_status
                )'
            );

            $counters = [
                'total_rows' => 0,
                'eligible_rows' => 0,
                'imported_count' => 0,
                'duplicate_count' => 0,
                'filtered_out_count' => 0,
            ];

            while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $counters['total_rows']++;
                $mappedRow = $this->mapRow($row, $headerMap);

                if ($mappedRow['sale_code'] === '' || ! $this->isEligible($mappedRow)) {
                    $counters['filtered_out_count']++;
                    continue;
                }

                $counters['eligible_rows']++;

                $insert->execute([
                    'import_batch_id' => $batchId,
                    'sale_code' => $mappedRow['sale_code'],
                    'pdv_adabas' => $mappedRow['pdv_adabas'],
                    'sale_input_date' => $mappedRow['sale_input_date'],
                    'sale_input_time' => $mappedRow['sale_input_time'],
                    'customer_document_type' => $mappedRow['customer_document_type'],
                    'customer_document' => $mappedRow['customer_document'],
                    'customer_name' => $mappedRow['customer_name'],
                    'phone_mobile' => $mappedRow['phone_mobile'],
                    'phone_alt_1' => $mappedRow['phone_alt_1'],
                    'phone_alt_2' => $mappedRow['phone_alt_2'],
                    'customer_email' => $mappedRow['customer_email'],
                    'customer_city' => $mappedRow['customer_city'],
                    'customer_uf' => $mappedRow['customer_uf'],
                    'customer_regional' => $mappedRow['customer_regional'],
                    'customer_cep' => $mappedRow['customer_cep'],
                    'service_type' => $mappedRow['service_type'],
                    'service_name' => $mappedRow['service_name'],
                    'plan_name' => $mappedRow['plan_name'],
                    'package_name' => $mappedRow['package_name'],
                    'composition_name' => $mappedRow['composition_name'],
                    'additional_services' => $mappedRow['additional_services'],
                    'additional_points' => $mappedRow['additional_points'],
                    'seller_cpf' => $mappedRow['seller_cpf'],
                    'sale_regional' => $mappedRow['sale_regional'],
                    'ddd' => $mappedRow['ddd'],
                    'service_order_number' => $mappedRow['service_order_number'],
                    'cabinet_code' => $mappedRow['cabinet_code'],
                    'sale_customer_type' => $mappedRow['sale_customer_type'],
                    'sale_status' => $mappedRow['sale_status'],
                    'service_status' => $mappedRow['service_status'],
                ]);

                if ($insert->rowCount() === 1) {
                    $counters['imported_count']++;
                } else {
                    $counters['duplicate_count']++;
                }
            }

            fclose($stream);
            $zip->close();

            $batchRepository->updateCounters($batchId, $counters);
            $connection->commit();

            return $counters;
        } catch (\Throwable $throwable) {
            fclose($stream);
            $zip->close();

            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    private function locateCsvName(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if ($entryName !== false && strtolower(basename($entryName)) === 'analitico_vendas_fixa.csv') {
                return $entryName;
            }
        }

        return null;
    }

    private function detectDelimiter(string $headerLine): string
    {
        $candidates = [
            ';' => substr_count($headerLine, ';'),
            ',' => substr_count($headerLine, ','),
            "\t" => substr_count($headerLine, "\t"),
        ];
        arsort($candidates);

        return (string) array_key_first($candidates);
    }

    private function mapRow(array $row, array $headerMap): array
    {
        $mapped = [];

        foreach (self::REQUIRED_COLUMNS as $csvColumn => $targetKey) {
            $mapped[$targetKey] = normalize_text((string) ($row[$headerMap[$csvColumn]] ?? ''));
        }

        $mapped['sale_code'] = trim($mapped['sale_code']);
        $mapped['sale_input_date'] = normalize_date_to_db($mapped['sale_input_date']);
        $mapped['sale_input_time'] = normalize_time_to_db($mapped['sale_input_time']);
        $mapped['customer_document_type'] = $this->mapCustomerDocumentType($mapped['customer_document_type']);
        $mapped['customer_name'] = $this->resolveCustomerName(
            $mapped['customer_name'],
            $mapped['customer_company_name'],
            $mapped['customer_document_type']
        );
        $mapped['customer_document'] = clean_document($mapped['customer_document']);
        $mapped['phone_mobile'] = clean_document($mapped['phone_mobile']);
        $mapped['phone_alt_1'] = clean_document($mapped['phone_alt_1']);
        $mapped['phone_alt_2'] = clean_document($mapped['phone_alt_2']);
        $mapped['customer_email'] = mb_strtolower($mapped['customer_email']);
        $mapped['customer_uf'] = strtoupper(substr(ascii_key($mapped['customer_uf']), 0, 2));
        $mapped['customer_cep'] = clean_document($mapped['customer_cep']);
        $mapped['seller_cpf'] = clean_document($mapped['seller_cpf']);
        $mapped['ddd'] = clean_document($mapped['ddd']);
        $mapped['sale_customer_type'] = normalize_text($mapped['sale_customer_type']);

        return $mapped;
    }

    private function resolveCustomerName(string $customerName, string $companyName, string $customerDocumentType): string
    {
        $customerName = normalize_text($customerName);
        $companyName = normalize_text($companyName);

        if ($customerDocumentType === 'B2B' && $companyName !== '') {
            return $companyName;
        }

        if (ascii_key($customerName) === 'nao se aplica' && $companyName !== '') {
            return $companyName;
        }

        return $customerName;
    }

    private function mapCustomerDocumentType(string $value): string
    {
        $normalized = ascii_key($value);

        return match ($normalized) {
            'pessoa juridica', 'cnpj', 'pj', 'b2b' => 'B2B',
            'pessoa fisica', 'cpf', 'pf', 'b2c' => 'B2C',
            default => mb_strtoupper(normalize_text($value)),
        };
    }

    private function isEligible(array $row): bool
    {
        return ascii_key($row['service_status']) === 'aprovado'
            && ascii_key($row['sale_status']) === 'finalizada'
            && ascii_key($row['service_name']) === 'banda larga';
    }
}
