<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

final class SalesRepository
{
    public function findAuditByQueueId(int $queueId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM sales_nexus
             WHERE IMPORT_QUEUE_ID = :queue_id
             ORDER BY ID ASC'
        );
        $statement->execute(['queue_id' => $queueId]);
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return null;
        }

        $firstRow = $rows[0];

        return [
            'service_order' => $firstRow['VENDA_ORDEM_DE_SERVICO'] ?? '',
            'sale_instance' => $firstRow['VENDA_INSTACIA'] ?? '',
            'sale_protocol' => $firstRow['VENDA_PROTOCOLO'] ?? '',
            'audit_installation_date' => $firstRow['VENDA_DATA_INSTALACAO_AUDITORIA'] ?? '',
            'customer_birth_date' => $firstRow['CLIENTE_DATA_NASCIMENTO'] ?? '',
            'customer_mother_name' => $firstRow['CLIENTE_MAE_NOME_FANTASIA'] ?? '',
            'phone_mobile' => $firstRow['CLIENTE_TELEFONE_CELULAR'] ?? '',
            'customer_street_name' => $firstRow['CLIENTE_LOGRADOURO_NOME'] ?? '',
            'customer_street_number' => $firstRow['CLIENTE_LOGRADOURO_NUMERO'] ?? '',
            'customer_street_complement' => $firstRow['CLIENTE_LOGRADOURO_COMPLEMENTO'] ?? '',
            'customer_neighborhood' => $firstRow['CLIENTE_BAIRRO_NOME'] ?? '',
            'customer_city' => $firstRow['CLIENTE_CIDADE_NOME'] ?? '',
            'customer_uf' => $firstRow['CLIENTE_CIDADE_UF'] ?? '',
            'consultant_name' => $firstRow['CONSULTOR_NOME'] ?? '',
            'consultant_cpf' => $firstRow['CONSULTOR_CPF'] ?? '',
            'consultant_type' => $firstRow['CONSULTOR_TIPO'] ?? '',
            'consultant_base_name' => $firstRow['CONSULTOR_BASE_NOME'] ?? '',
            'consultant_base_group' => $firstRow['CONSULTOR_BASE_GRUPO'] ?? '',
            'consultant_base_regional' => $firstRow['CONSULTOR_BASE_REGIONAL'] ?? '',
            'consultant_sector_name' => $firstRow['CONSULTOR_SETOR_NOME'] ?? '',
            'consultant_sector_type' => $firstRow['CONSULTOR_SETOR_TIPO'] ?? '',
            'supervisor_name' => $firstRow['SUPERVISOR_NOME'] ?? '',
            'coordinator_name' => $firstRow['COORDENADOR_NOME'] ?? '',
            'base_manager_name' => $firstRow['GERENTE_BASE_NOME'] ?? '',
            'territory_manager_name' => $firstRow['GERENTE_TERRITORIO_NOME'] ?? '',
            'sale_type' => $firstRow['VENDA_TIPO_DE_VENDA'] ?? '',
            'product_names' => array_values(array_filter(array_map(
                static fn (array $row): string => normalize_text((string) ($row['PRODUTO_NOME'] ?? '')),
                $rows
            ))),
        ];
    }

    public function saveAudit(array $queueItem, array $formData, array $products, int $userId, string $auditorName, array $changes = []): void
    {
        if ($products === []) {
            throw new RuntimeException('Selecione ao menos um produto para finalizar.');
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $checkStatement = $connection->prepare('SELECT * FROM sales_import_queue WHERE id = :id FOR UPDATE');
            $checkStatement->execute(['id' => $queueItem['id']]);
            $lockedQueueItem = $checkStatement->fetch();

            if ($lockedQueueItem === false) {
                throw new RuntimeException('Venda não encontrada para finalização.');
            }

            $isEditingFinalizedSale = $lockedQueueItem['audit_status'] === 'FINALIZADA';

            if (! $isEditingFinalizedSale && $lockedQueueItem['claimed_by_user_id'] !== null && (int) $lockedQueueItem['claimed_by_user_id'] !== $userId) {
                throw new RuntimeException('Essa venda está reservada para outro usuário.');
            }

            $existingSalesStatement = $connection->prepare(
                'SELECT ID
                 FROM sales_nexus
                 WHERE IMPORT_QUEUE_ID = :queue_id
                 FOR UPDATE'
            );
            $existingSalesStatement->execute(['queue_id' => $lockedQueueItem['id']]);
            $existingSales = $existingSalesStatement->fetchAll();

            if ($existingSales !== []) {
                $deleteStatement = $connection->prepare(
                    'DELETE FROM sales_nexus WHERE IMPORT_QUEUE_ID = :queue_id'
                );
                $deleteStatement->execute(['queue_id' => $lockedQueueItem['id']]);
            }

            $insert = $connection->prepare(
                'INSERT INTO sales_nexus (
                    IMPORT_QUEUE_ID, USUARIO_FINALIZACAO_ID, VENDA_ID, VENDA_ORDEM_DE_SERVICO,
                    POSVENDA_STATUS, POSVENDA_DATA_INSTALACAO, POSVENDA_SUB_STATUS, VENDA_DATA_INPUT,
                    VENDA_DATA_INPUT_MES, VENDA_DATA_INSTALACAO_AUDITORIA, CLIENTE_DOCUMENTO,
                    CLIENTE_NOME_RAZAO_SOCIAL, CLIENTE_TIPO_DOCUMENTO, CLIENTE_DATA_NASCIMENTO,
                    CLIENTE_MAE_NOME_FANTASIA, CLIENTE_TELEFONE_CELULAR, AUDITOR_NOME,
                    VENDA_INSTACIA, VENDA_PROTOCOLO, CLIENTE_CIDADE_UF, CLIENTE_CIDADE_NOME,
                    CLIENTE_LOGRADOURO_NOME, CLIENTE_LOGRADOURO_NUMERO,
                    CLIENTE_LOGRADOURO_COMPLEMENTO, CLIENTE_BAIRRO_NOME, CNLAT_TERRITORIO, VENDA_MODALIDADE, PRODUTO_NOME,
                    PRODUTO_PONTUACAO_COMERCIAL, PRODUTO_TIPO, PRODUTO_CATEGORIA,
                    PRODUTO_TECNOLOGIA, PRODUTO_NOME_TIPO, CONSULTOR_NOME, CONSULTOR_TIPO,
                    CONSULTOR_CPF, CONSULTOR_BASE_NOME, CONSULTOR_BASE_GRUPO,
                    CONSULTOR_SETOR_NOME, CONSULTOR_SETOR_TIPO, SUPERVISOR_NOME,
                    COORDENADOR_NOME, GERENTE_BASE_NOME, GERENTE_TERRITORIO_NOME, DIRETOR_NOME,
                    VENDA_TIPO_DE_VENDA, CONSULTOR_BASE_REGIONAL, PRODUTO_VALOR_GERENCIAL,
                    SGV_ADABAS_FIXA, VENDA_PERIODO_INPUT
                ) VALUES (
                    :IMPORT_QUEUE_ID, :USUARIO_FINALIZACAO_ID, :VENDA_ID, :VENDA_ORDEM_DE_SERVICO,
                    :POSVENDA_STATUS, :POSVENDA_DATA_INSTALACAO, :POSVENDA_SUB_STATUS, :VENDA_DATA_INPUT,
                    :VENDA_DATA_INPUT_MES, :VENDA_DATA_INSTALACAO_AUDITORIA, :CLIENTE_DOCUMENTO,
                    :CLIENTE_NOME_RAZAO_SOCIAL, :CLIENTE_TIPO_DOCUMENTO, :CLIENTE_DATA_NASCIMENTO,
                    :CLIENTE_MAE_NOME_FANTASIA, :CLIENTE_TELEFONE_CELULAR, :AUDITOR_NOME,
                    :VENDA_INSTACIA, :VENDA_PROTOCOLO, :CLIENTE_CIDADE_UF, :CLIENTE_CIDADE_NOME,
                    :CLIENTE_LOGRADOURO_NOME, :CLIENTE_LOGRADOURO_NUMERO,
                    :CLIENTE_LOGRADOURO_COMPLEMENTO, :CLIENTE_BAIRRO_NOME, :CNLAT_TERRITORIO, :VENDA_MODALIDADE, :PRODUTO_NOME,
                    :PRODUTO_PONTUACAO_COMERCIAL, :PRODUTO_TIPO, :PRODUTO_CATEGORIA,
                    :PRODUTO_TECNOLOGIA, :PRODUTO_NOME_TIPO, :CONSULTOR_NOME, :CONSULTOR_TIPO,
                    :CONSULTOR_CPF, :CONSULTOR_BASE_NOME, :CONSULTOR_BASE_GRUPO,
                    :CONSULTOR_SETOR_NOME, :CONSULTOR_SETOR_TIPO, :SUPERVISOR_NOME,
                    :COORDENADOR_NOME, :GERENTE_BASE_NOME, :GERENTE_TERRITORIO_NOME, :DIRETOR_NOME,
                    :VENDA_TIPO_DE_VENDA, :CONSULTOR_BASE_REGIONAL, :PRODUTO_VALOR_GERENCIAL,
                    :SGV_ADABAS_FIXA, :VENDA_PERIODO_INPUT
                )'
            );

            $inputDate = normalize_date_to_db((string) $queueItem['sale_input_date']);
            $inputPeriod = $inputDate !== null ? date('Ym', strtotime($inputDate)) : null;
            $birthDate = normalize_date_to_db((string) ($formData['customer_birth_date'] ?? ''));

            foreach ($products as $product) {
                $postSaleStatus = $this->postSaleStatusByProduct($product);

                $insert->execute([
                    'IMPORT_QUEUE_ID' => (int) $queueItem['id'],
                    'USUARIO_FINALIZACAO_ID' => $userId,
                    'VENDA_ID' => $queueItem['sale_code'],
                    'VENDA_ORDEM_DE_SERVICO' => $formData['service_order'] ?: null,
                    'POSVENDA_STATUS' => $postSaleStatus,
                    'POSVENDA_DATA_INSTALACAO' => null,
                    'POSVENDA_SUB_STATUS' => $postSaleStatus,
                    'VENDA_DATA_INPUT' => $inputDate,
                    'VENDA_DATA_INPUT_MES' => $inputDate !== null ? (int) date('n', strtotime($inputDate)) : null,
                    'VENDA_DATA_INSTALACAO_AUDITORIA' => $formData['audit_installation_date'] ?: null,
                    'CLIENTE_DOCUMENTO' => $formData['customer_document'] ?: $queueItem['customer_document'],
                    'CLIENTE_NOME_RAZAO_SOCIAL' => $formData['customer_name'] ?: $queueItem['customer_name'],
                    'CLIENTE_TIPO_DOCUMENTO' => $queueItem['customer_document_type'],
                    'CLIENTE_DATA_NASCIMENTO' => $birthDate,
                    'CLIENTE_MAE_NOME_FANTASIA' => $formData['customer_mother_name'] ?: null,
                    'CLIENTE_TELEFONE_CELULAR' => $formData['phone_mobile'] ?: $queueItem['phone_mobile'],
                    'AUDITOR_NOME' => $auditorName !== '' ? $auditorName : null,
                    'VENDA_INSTACIA' => $formData['sale_instance'] ?: null,
                    'VENDA_PROTOCOLO' => $formData['sale_protocol'] ?: null,
                    'CLIENTE_CIDADE_UF' => $formData['customer_uf'] ?: $queueItem['customer_uf'],
                    'CLIENTE_CIDADE_NOME' => $formData['customer_city'] ?: $queueItem['customer_city'],
                    'CLIENTE_LOGRADOURO_NOME' => $formData['customer_street_name'] ?: null,
                    'CLIENTE_LOGRADOURO_NUMERO' => $formData['customer_street_number'] ?: null,
                    'CLIENTE_LOGRADOURO_COMPLEMENTO' => $formData['customer_street_complement'] ?: null,
                    'CLIENTE_BAIRRO_NOME' => $formData['customer_neighborhood'] ?: null,
                    'CNLAT_TERRITORIO' => $formData['customer_regional'] ?: $queueItem['customer_regional'],
                    'VENDA_MODALIDADE' => $formData['service_type'] ?: $queueItem['service_type'],
                    'PRODUTO_NOME' => $product['PRODUTO_NOME'] ?? $product['name'],
                    'PRODUTO_PONTUACAO_COMERCIAL' => $product['PRODUTO_PONTUACAO_COMERCIAL'] ?? $product['commercial_score'],
                    'PRODUTO_TIPO' => $product['TIPO'] ?? $product['type'],
                    'PRODUTO_CATEGORIA' => $product['CATEGORIA'] ?? $product['category'],
                    'PRODUTO_TECNOLOGIA' => null,
                    'PRODUTO_NOME_TIPO' => $product['TIPO_DOC'] ?? $product['document_type'] ?? null,
                    'CONSULTOR_NOME' => $formData['consultant_name'] ?: null,
                    'CONSULTOR_TIPO' => $formData['consultant_type'] ?: null,
                    'CONSULTOR_CPF' => $formData['consultant_cpf'] ?: $queueItem['seller_cpf'],
                    'CONSULTOR_BASE_NOME' => $formData['consultant_base_name'] ?: null,
                    'CONSULTOR_BASE_GRUPO' => $formData['consultant_base_group'] ?: null,
                    'CONSULTOR_BASE_REGIONAL' => $formData['consultant_base_regional'] ?: ($queueItem['sale_regional'] ?: null),
                    'CONSULTOR_SETOR_NOME' => $formData['consultant_sector_name'] ?: null,
                    'CONSULTOR_SETOR_TIPO' => $formData['consultant_sector_type'] ?: null,
                    'SUPERVISOR_NOME' => $formData['supervisor_name'] ?: null,
                    'COORDENADOR_NOME' => $formData['coordinator_name'] ?: null,
                    'GERENTE_BASE_NOME' => $formData['base_manager_name'] ?: null,
                    'GERENTE_TERRITORIO_NOME' => $formData['territory_manager_name'] ?: null,
                    'DIRETOR_NOME' => $formData['director_name'] ?: null,
                    'VENDA_TIPO_DE_VENDA' => $formData['sale_type'] ?: null,
                    'PRODUTO_VALOR_GERENCIAL' => $product['PRODUTO_VALOR_GERENCIAL'] ?? $product['managerial_value'],
                    'SGV_ADABAS_FIXA' => $formData['pdv_adabas'] ?: $queueItem['pdv_adabas'],
                    'VENDA_PERIODO_INPUT' => $inputPeriod,
                ]);
            }

            $updateQueueSourceData = $connection->prepare(
                'UPDATE sales_import_queue
                 SET pdv_adabas = :pdv_adabas,
                     customer_document = :customer_document,
                     customer_name = :customer_name,
                     phone_mobile = :phone_mobile,
                     phone_alt_1 = :phone_alt_1,
                     phone_alt_2 = :phone_alt_2,
                     customer_email = :customer_email,
                     customer_cep = :customer_cep,
                     customer_city = :customer_city,
                     customer_uf = :customer_uf,
                     customer_regional = :customer_regional,
                     service_type = :service_type,
                     service_name = :service_name,
                     plan_name = :plan_name,
                     package_name = :package_name,
                     composition_name = :composition_name,
                     sale_regional = :sale_regional,
                     ddd = :ddd,
                     service_order_number = :service_order_number,
                     cabinet_code = :cabinet_code,
                     sale_customer_type = :sale_customer_type
                 WHERE id = :id'
            );
            $updateQueueSourceData->execute([
                'id' => $queueItem['id'],
                'pdv_adabas' => $formData['pdv_adabas'] ?: $queueItem['pdv_adabas'],
                'customer_document' => $formData['customer_document'] ?: $queueItem['customer_document'],
                'customer_name' => $formData['customer_name'] ?: $queueItem['customer_name'],
                'phone_mobile' => $formData['phone_mobile'] ?: $queueItem['phone_mobile'],
                'phone_alt_1' => $formData['phone_alt_1'] ?: $queueItem['phone_alt_1'],
                'phone_alt_2' => $formData['phone_alt_2'] ?: $queueItem['phone_alt_2'],
                'customer_email' => $formData['customer_email'] ?: $queueItem['customer_email'],
                'customer_cep' => $formData['customer_cep'] ?: $queueItem['customer_cep'],
                'customer_city' => $formData['customer_city'] ?: $queueItem['customer_city'],
                'customer_uf' => $formData['customer_uf'] ?: $queueItem['customer_uf'],
                'customer_regional' => $formData['customer_regional'] ?: $queueItem['customer_regional'],
                'service_type' => $formData['service_type'] ?: $queueItem['service_type'],
                'service_name' => $formData['service_name'] ?: $queueItem['service_name'],
                'plan_name' => $formData['plan_name'] ?: $queueItem['plan_name'],
                'package_name' => $formData['package_name'] ?: $queueItem['package_name'],
                'composition_name' => $formData['composition_name'] ?: $queueItem['composition_name'],
                'sale_regional' => $formData['sale_regional'] ?: $queueItem['sale_regional'],
                'ddd' => $formData['ddd'] ?: $queueItem['ddd'],
                'service_order_number' => $formData['service_order'] ?: $queueItem['service_order_number'],
                'cabinet_code' => $formData['cabinet_code'] ?: $queueItem['cabinet_code'],
                'sale_customer_type' => $formData['sale_type'] ?: $queueItem['sale_customer_type'],
            ]);

            $updateQueue = $connection->prepare(
                "UPDATE sales_import_queue
                 SET audit_status = 'FINALIZADA',
                     finalized_at = COALESCE(finalized_at, NOW()),
                     claimed_by_user_id = :user_id,
                     claimed_at = COALESCE(claimed_at, NOW())
                 WHERE id = :id"
            );
            $updateQueue->execute([
                'id' => $queueItem['id'],
                'user_id' => $userId,
            ]);

            $saleLogRepository = new SaleLogRepository();
            $saleLogRepository->logChanges($lockedQueueItem, $userId, $changes, $connection);

            if (! $isEditingFinalizedSale) {
                $saleLogRepository->logFinalization($lockedQueueItem, $userId, count($products), $connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    private function postSaleStatusByProduct(array $product): string
    {
        $category = normalize_text((string) ($product['CATEGORIA'] ?? $product['category'] ?? ''));

        return $category === 'MOVEL' ? 'Movel' : 'Pendente';
    }
}
