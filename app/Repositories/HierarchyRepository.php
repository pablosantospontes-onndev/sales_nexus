<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

final class HierarchyRepository
{
    private const ALLOWED_ROLES = ['CONSULTOR', 'SUPERVISOR', 'COORDENADOR', 'GERENTE'];
    private const ALLOWED_BASE_REGIONALS = ['I', 'II'];

    public function latestHeadcountPeriod(): ?string
    {
        $period = Database::connection()
            ->query('SELECT MAX(PERIODO_HEADCOUNT) FROM seller_hierarchies')
            ->fetchColumn();

        $period = is_string($period) ? trim($period) : '';

        return preg_match('/^\d{6}$/', $period) ? $period : null;
    }

    public function headcountPeriods(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT PERIODO_HEADCOUNT
             FROM seller_hierarchies
             WHERE PERIODO_HEADCOUNT IS NOT NULL
               AND TRIM(PERIODO_HEADCOUNT) <> \'\'
             ORDER BY PERIODO_HEADCOUNT DESC'
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['PERIODO_HEADCOUNT'] ?? ''),
            $statement->fetchAll()
        )));
    }

    public function activeHeadcountEdit(): ?array
    {
        $statement = Database::connection()->query(
            'SELECT hierarchy_headcount_edits.*, users.name AS downloaded_by_name
             FROM hierarchy_headcount_edits
             INNER JOIN users ON users.id = hierarchy_headcount_edits.downloaded_by_user_id
             WHERE hierarchy_headcount_edits.is_active = 1
             ORDER BY hierarchy_headcount_edits.downloaded_at DESC, hierarchy_headcount_edits.id DESC
             LIMIT 1'
        );

        return $statement->fetch() ?: null;
    }

    public function registerHeadcountDownload(int $userId, string $periodHeadcount): void
    {
        if ($userId <= 0 || ! preg_match('/^\d{6}$/', $periodHeadcount)) {
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $connection->exec(
                'UPDATE hierarchy_headcount_edits
                 SET is_active = 0
                 WHERE is_active = 1'
            );

            $statement = $connection->prepare(
                'INSERT INTO hierarchy_headcount_edits (
                    period_headcount,
                    downloaded_by_user_id,
                    downloaded_at,
                    is_active
                ) VALUES (
                    :period_headcount,
                    :downloaded_by_user_id,
                    NOW(),
                    1
                )'
            );
            $statement->execute([
                'period_headcount' => $periodHeadcount,
                'downloaded_by_user_id' => $userId,
            ]);

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function resolveHeadcountEditAfterImport(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE hierarchy_headcount_edits
             SET is_active = 0,
                 uploaded_at = NOW(),
                 resolved_by_user_id = :resolved_by_user_id,
                 resolved_at = NOW()
             WHERE is_active = 1
               AND downloaded_by_user_id = :downloaded_by_user_id
               AND downloaded_at <= NOW()'
        );
        $statement->execute([
            'resolved_by_user_id' => $userId,
            'downloaded_by_user_id' => $userId,
        ]);
    }

    public function roles(): array
    {
        return self::ALLOWED_ROLES;
    }

    public function stats(): array
    {
        $connection = Database::connection();
        $latestPeriod = $this->latestHeadcountPeriod();
        $headcountStatsStatement = $latestPeriod !== null
            ? $connection->prepare(
                'SELECT
                    COUNT(*) AS sellers,
                    COUNT(DISTINCT base_id) AS bases,
                    COUNT(DISTINCT base_group_id) AS groups,
                    SUM(CASE WHEN role = \'GERENTE\' THEN 1 ELSE 0 END) AS managers,
                    SUM(CASE WHEN role = \'SUPERVISOR\' THEN 1 ELSE 0 END) AS supervisors,
                    SUM(CASE WHEN role = \'COORDENADOR\' THEN 1 ELSE 0 END) AS coordinators
                 FROM seller_hierarchies
                 WHERE PERIODO_HEADCOUNT = :period'
            )
            : null;

        if ($headcountStatsStatement instanceof \PDOStatement) {
            $headcountStatsStatement->execute(['period' => $latestPeriod]);
        }

        $headcountStats = $headcountStatsStatement instanceof \PDOStatement
            ? ($headcountStatsStatement->fetch() ?: [])
            : [];

        return [
            'bases' => $latestPeriod !== null
                ? (int) ($headcountStats['bases'] ?? 0)
                : (int) $connection->query('SELECT COUNT(*) FROM hierarchy_bases')->fetchColumn(),
            'groups' => $latestPeriod !== null
                ? (int) ($headcountStats['groups'] ?? 0)
                : (int) $connection->query('SELECT COUNT(*) FROM hierarchy_base_groups')->fetchColumn(),
            'sellers' => $latestPeriod !== null
                ? (int) ($headcountStats['sellers'] ?? 0)
                : (int) $connection->query('SELECT COUNT(*) FROM seller_hierarchies')->fetchColumn(),
            'managers' => $latestPeriod !== null ? (int) ($headcountStats['managers'] ?? 0) : 0,
            'supervisors' => $latestPeriod !== null ? (int) ($headcountStats['supervisors'] ?? 0) : 0,
            'coordinators' => $latestPeriod !== null ? (int) ($headcountStats['coordinators'] ?? 0) : 0,
        ];
    }

    public function bases(): array
    {
        return Database::connection()
            ->query('SELECT * FROM hierarchy_bases ORDER BY name ASC')
            ->fetchAll();
    }

    public function groups(?int $baseId = null): array
    {
        $sql = 'SELECT hierarchy_base_groups.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional
                FROM hierarchy_base_groups
                LEFT JOIN hierarchy_bases ON hierarchy_bases.id = hierarchy_base_groups.base_id
                WHERE 1 = 1';
        $bindings = [];

        if ($baseId !== null && $baseId > 0) {
            $sql .= ' AND hierarchy_base_groups.base_id = :base_id';
            $bindings['base_id'] = $baseId;
        }

        $sql .= ' ORDER BY hierarchy_bases.name ASC, hierarchy_base_groups.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function groupOptionsByBase(): array
    {
        $options = [];

        foreach ($this->groups() as $group) {
            $baseId = (int) ($group['base_id'] ?? 0);

            if ($baseId <= 0) {
                continue;
            }

            $options[(string) $baseId][] = [
                'id' => (int) $group['id'],
                'name' => (string) $group['name'],
            ];
        }

        return $options;
    }

    public function sellers(
        ?string $term = null,
        array $baseIds = [],
        array $roles = [],
        int $page = 1,
        int $perPage = 50,
        ?string $periodHeadcount = null
    ): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        [$baseSql, $filtersSql, $bindings] = $this->buildSellerFilters($term, $baseIds, $roles, $periodHeadcount);

        $countStatement = Database::connection()->prepare(
            'SELECT COUNT(*)
            ' . $baseSql . $filtersSql
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT seller_hierarchies.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name
                ' . $baseSql . $filtersSql . '
                ORDER BY seller_hierarchies.seller_name ASC
                LIMIT :limit OFFSET :offset';

        $statement = Database::connection()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    public function exportRows(?string $term = null, array $baseIds = [], array $roles = [], ?string $periodHeadcount = null): array
    {
        [$baseSql, $filtersSql, $bindings] = $this->buildSellerFilters($term, $baseIds, $roles, $periodHeadcount);

        $sql = 'SELECT
                    seller_hierarchies.seller_name,
                    seller_hierarchies.seller_cpf,
                    seller_hierarchies.PERIODO_HEADCOUNT,
                    seller_hierarchies.role,
                    hierarchy_bases.name AS base_name,
                    hierarchy_bases.REGIONAL AS base_regional,
                    hierarchy_base_groups.name AS base_group_name,
                    seller_hierarchies.CONSULTOR_BASE_REGIONAL,
                    seller_hierarchies.CONSULTOR_SETOR_NOME,
                    seller_hierarchies.CONSULTOR_SETOR_TIPO,
                    seller_hierarchies.supervisor_name,
                    seller_hierarchies.SUPERVISOR_CPF,
                    seller_hierarchies.coordinator_name,
                    seller_hierarchies.COORDENADOR_CPF,
                    seller_hierarchies.manager_name,
                    seller_hierarchies.GERENTE_BASE_CPF,
                    seller_hierarchies.GERENTE_TERRITORIO_NOME,
                    seller_hierarchies.GERENTE_TERRITORIO_CPF,
                    seller_hierarchies.created_at,
                    seller_hierarchies.updated_at
                ' . $baseSql . $filtersSql . '
                ORDER BY seller_hierarchies.seller_name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function findBase(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM hierarchy_bases WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function findGroup(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT hierarchy_base_groups.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional
             FROM hierarchy_base_groups
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = hierarchy_base_groups.base_id
             WHERE hierarchy_base_groups.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function findSeller(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT seller_hierarchies.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name
             FROM seller_hierarchies
             INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
             INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
             WHERE seller_hierarchies.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function findBySellerCpf(string $sellerCpf, ?string $periodHeadcount = null): ?array
    {
        $sql = 'SELECT seller_hierarchies.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name
                FROM seller_hierarchies
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE seller_hierarchies.seller_cpf = :seller_cpf';
        $bindings = ['seller_cpf' => clean_document($sellerCpf)];

        if ($periodHeadcount !== null && preg_match('/^\d{6}$/', $periodHeadcount)) {
            $sql .= ' AND seller_hierarchies.PERIODO_HEADCOUNT = :period_headcount';
            $bindings['period_headcount'] = $periodHeadcount;
        }

        $sql .= ' ORDER BY seller_hierarchies.PERIODO_HEADCOUNT DESC, seller_hierarchies.id DESC LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetch() ?: null;
    }

    public function lookupSellers(string $term, int $limit = 12, ?string $periodHeadcount = null): array
    {
        $term = normalize_text($term);

        if ($term === '') {
            return [];
        }

        $normalizedPeriod = preg_match('/^\d{6}$/', (string) $periodHeadcount)
            ? (string) $periodHeadcount
            : null;
        $items = $this->lookupSellersQuery($term, max(1, $limit), $normalizedPeriod);

        if ($items !== [] || $normalizedPeriod === null) {
            return $items;
        }

        $fallbackItems = $this->lookupSellersQuery($term, max($limit * 4, 24), null);

        if ($fallbackItems === []) {
            return [];
        }

        $deduplicatedItems = [];
        $seenSellerCpfs = [];

        foreach ($fallbackItems as $item) {
            $sellerCpf = clean_document((string) ($item['seller_cpf'] ?? ''));
            $sellerKey = $sellerCpf !== '' ? $sellerCpf : 'row_' . (string) ($item['id'] ?? count($deduplicatedItems));

            if (isset($seenSellerCpfs[$sellerKey])) {
                continue;
            }

            $seenSellerCpfs[$sellerKey] = true;
            $deduplicatedItems[] = $item;

            if (count($deduplicatedItems) >= $limit) {
                break;
            }
        }

        return $deduplicatedItems;
    }

    private function lookupSellersQuery(string $term, int $limit, ?string $periodHeadcount = null): array
    {
        $cleanTerm = clean_document($term);
        $sql = 'SELECT seller_hierarchies.*, hierarchy_bases.name AS base_name, hierarchy_bases.REGIONAL AS base_regional, hierarchy_base_groups.name AS base_group_name
                FROM seller_hierarchies
                INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                WHERE (seller_hierarchies.seller_name LIKE :term';

        if ($cleanTerm !== '') {
            $sql .= ' OR seller_hierarchies.seller_cpf LIKE :clean_term';
        }

        $sql .= ')';

        if ($periodHeadcount !== null) {
            $sql .= ' AND seller_hierarchies.PERIODO_HEADCOUNT = :period_headcount';
        }

        $sql .= ' ORDER BY seller_hierarchies.seller_name ASC, seller_hierarchies.PERIODO_HEADCOUNT DESC
                  LIMIT :limit';

        $statement = Database::connection()->prepare($sql);
        $statement->bindValue(':term', '%' . $term . '%');

        if ($cleanTerm !== '') {
            $statement->bindValue(':clean_term', '%' . $cleanTerm . '%');
        }

        if ($periodHeadcount !== null) {
            $statement->bindValue(':period_headcount', $periodHeadcount);
        }

        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function saveBase(?int $id, string $name, ?string $regional): int
    {
        $name = $this->normalizeUpperText($name);
        $regional = strtoupper(normalize_text($regional));

        if ($name === '') {
            throw new RuntimeException('Informe o nome da operação/base.');
        }

        if (! in_array($regional, self::ALLOWED_BASE_REGIONALS, true)) {
            throw new RuntimeException('Selecione a regional da operação/base.');
        }

        $existing = $this->findBaseByName($name);
        if ($existing !== null && (int) $existing['id'] !== (int) $id) {
            throw new RuntimeException('Já existe uma operação/base com esse nome.');
        }

        if ($id !== null && $id > 0) {
            $statement = Database::connection()->prepare(
                'UPDATE hierarchy_bases SET name = :name, REGIONAL = :regional WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'regional' => $regional,
            ]);

            return $id;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO hierarchy_bases (name, REGIONAL) VALUES (:name, :regional)'
        );
        $statement->execute([
            'name' => $name,
            'regional' => $regional,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function saveGroup(?int $id, int $baseId, string $name): int
    {
        $name = $this->normalizeUpperText($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome do base grupo.');
        }

        if ($baseId <= 0 || $this->findBase($baseId) === null) {
            throw new RuntimeException('Selecione uma operação/base válida para o base grupo.');
        }

        $existing = $this->findGroupByName($name);
        if ($existing !== null && (int) $existing['id'] !== (int) $id) {
            throw new RuntimeException('Já existe um base grupo com esse nome.');
        }

        if ($id !== null && $id > 0) {
            $statement = Database::connection()->prepare(
                'UPDATE hierarchy_base_groups
                 SET name = :name,
                     base_id = :base_id
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'base_id' => $baseId,
            ]);

            Database::connection()
                ->prepare('UPDATE seller_hierarchies SET base_id = :base_id WHERE base_group_id = :base_group_id')
                ->execute([
                    'base_id' => $baseId,
                    'base_group_id' => $id,
                ]);

            return $id;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO hierarchy_base_groups (name, base_id) VALUES (:name, :base_id)'
        );
        $statement->execute([
            'name' => $name,
            'base_id' => $baseId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function deleteBase(int $id): void
    {
        $base = $this->findBase($id);

        if ($base === null) {
            throw new RuntimeException('Operação/base não encontrada.');
        }

        $connection = Database::connection();

        $groupsCountStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM hierarchy_base_groups
             WHERE base_id = :base_id'
        );
        $groupsCountStatement->execute(['base_id' => $id]);
        $groupsCount = (int) $groupsCountStatement->fetchColumn();

        if ($groupsCount > 0) {
            throw new RuntimeException('Não é possível excluir esta operação/base porque ela possui base grupos vinculados.');
        }

        $sellersCountStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM seller_hierarchies
             WHERE base_id = :base_id'
        );
        $sellersCountStatement->execute(['base_id' => $id]);
        $sellersCount = (int) $sellersCountStatement->fetchColumn();

        if ($sellersCount > 0) {
            throw new RuntimeException('Não é possível excluir esta operação/base porque ela possui vendedores vinculados.');
        }

        $connection->prepare('DELETE FROM hierarchy_bases WHERE id = :id')->execute(['id' => $id]);
    }

    public function deleteGroup(int $id): void
    {
        $group = $this->findGroup($id);

        if ($group === null) {
            throw new RuntimeException('Base grupo não encontrado.');
        }

        $connection = Database::connection();
        $sellersCountStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM seller_hierarchies
             WHERE base_group_id = :base_group_id'
        );
        $sellersCountStatement->execute(['base_group_id' => $id]);
        $sellersCount = (int) $sellersCountStatement->fetchColumn();

        if ($sellersCount > 0) {
            throw new RuntimeException('Não é possível excluir este base grupo porque ele possui vendedores vinculados.');
        }

        $connection->prepare('DELETE FROM hierarchy_base_groups WHERE id = :id')->execute(['id' => $id]);
    }

    public function saveSeller(?int $id, array $data, int $userId): int
    {
        $currentSeller = $id !== null && $id > 0 ? $this->findSeller($id) : null;
        $sellerName = $this->normalizeUpperText($data['seller_name'] ?? '');
        $sellerCpf = clean_document($data['seller_cpf'] ?? '');
        $periodHeadcount = clean_document($data['period_headcount'] ?? '');
        $role = strtoupper(normalize_text($data['role'] ?? 'CONSULTOR'));
        $supervisorName = $this->normalizeUpperText($data['supervisor_name'] ?? '');
        $coordinatorName = $this->normalizeUpperText($data['coordinator_name'] ?? '');
        $managerName = $this->normalizeUpperText($data['manager_name'] ?? '');
        $baseId = (int) ($data['base_id'] ?? 0);
        $baseGroupId = (int) ($data['base_group_id'] ?? 0);
        $consultantBaseRegional = $this->normalizeNullableUpperText($data['consultant_base_regional'] ?? '');
        $consultantSectorName = $this->normalizeNullableUpperText($data['consultant_sector_name'] ?? '');
        $consultantSectorType = $this->normalizeNullableUpperText($data['consultant_sector_type'] ?? '');
        $territoryManagerName = $this->normalizeNullableUpperText($data['territory_manager_name'] ?? '');
        $supervisorCpf = $this->resolveLinkedCpf(
            $supervisorName,
            'SUPERVISOR',
            $periodHeadcount,
            $sellerName,
            $sellerCpf,
            $currentSeller['supervisor_name'] ?? null,
            $currentSeller['SUPERVISOR_CPF'] ?? null
        );
        $coordinatorCpf = $this->resolveLinkedCpf(
            $coordinatorName,
            'COORDENADOR',
            $periodHeadcount,
            $sellerName,
            $sellerCpf,
            $currentSeller['coordinator_name'] ?? null,
            $currentSeller['COORDENADOR_CPF'] ?? null
        );
        $managerCpf = $this->resolveLinkedCpf(
            $managerName,
            'GERENTE',
            $periodHeadcount,
            $sellerName,
            $sellerCpf,
            $currentSeller['manager_name'] ?? null,
            $currentSeller['GERENTE_BASE_CPF'] ?? null
        );
        $territoryManagerCpf = $this->resolveLinkedCpf(
            (string) ($territoryManagerName ?? ''),
            'GERENTE',
            $periodHeadcount,
            $sellerName,
            $sellerCpf,
            $currentSeller['GERENTE_TERRITORIO_NOME'] ?? null,
            $currentSeller['GERENTE_TERRITORIO_CPF'] ?? null
        );

        if ($sellerName === '' || $sellerCpf === '' || $supervisorName === '' || $coordinatorName === '' || $managerName === '') {
            throw new RuntimeException('Preencha vendedor, CPF, supervisor, coordenador e gerente.');
        }

        if (strlen($sellerCpf) !== 11) {
            throw new RuntimeException('Informe o CPF do vendedor com 11 números.');
        }

        if (! valid_cpf($sellerCpf)) {
            throw new RuntimeException('CPF inválido.');
        }

        if (! preg_match('/^\d{6}$/', $periodHeadcount)) {
            throw new RuntimeException('Informe o período headcount no formato YYYYMM.');
        }

        $periodMonth = (int) substr($periodHeadcount, 4, 2);

        if ($periodMonth < 1 || $periodMonth > 12) {
            throw new RuntimeException('Informe um período headcount válido.');
        }

        if ($supervisorCpf !== '' && strlen($supervisorCpf) !== 11) {
            throw new RuntimeException('Informe o CPF do supervisor com 11 números.');
        }

        if ($supervisorCpf !== '' && ! valid_cpf($supervisorCpf)) {
            throw new RuntimeException('CPF do supervisor inválido.');
        }

        if ($coordinatorCpf !== '' && strlen($coordinatorCpf) !== 11) {
            throw new RuntimeException('Informe o CPF do coordenador com 11 números.');
        }

        if ($coordinatorCpf !== '' && ! valid_cpf($coordinatorCpf)) {
            throw new RuntimeException('CPF do coordenador inválido.');
        }

        if ($managerCpf !== '' && strlen($managerCpf) !== 11) {
            throw new RuntimeException('Informe o CPF do gerente com 11 números.');
        }

        if ($managerCpf !== '' && ! valid_cpf($managerCpf)) {
            throw new RuntimeException('CPF do gerente inválido.');
        }

        if ($territoryManagerCpf !== '' && strlen($territoryManagerCpf) !== 11) {
            throw new RuntimeException('Informe o CPF do gerente território com 11 números.');
        }

        if ($territoryManagerCpf !== '' && ! valid_cpf($territoryManagerCpf)) {
            throw new RuntimeException('CPF do gerente território inválido.');
        }

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new RuntimeException('Selecione um tipo hierárquico válido.');
        }

        if ($baseId <= 0 || $this->findBase($baseId) === null) {
            throw new RuntimeException('Selecione uma operação/base válida.');
        }

        $selectedGroup = $baseGroupId > 0 ? $this->findGroup($baseGroupId) : null;

        if ($selectedGroup === null) {
            throw new RuntimeException('Selecione um base grupo válido.');
        }

        if ((int) ($selectedGroup['base_id'] ?? 0) !== $baseId) {
            throw new RuntimeException('O base grupo selecionado não pertence à operação/base escolhida.');
        }

        $existing = $this->findSellerByCpf($sellerCpf, $periodHeadcount);
        if ($existing !== null && (int) $existing['id'] !== (int) $id) {
            throw new RuntimeException('Já existe um vendedor cadastrado com esse CPF nesse período.');
        }

        if ($id !== null && $id > 0) {
            $statement = Database::connection()->prepare(
                'UPDATE seller_hierarchies
                 SET seller_name = :seller_name,
                     seller_cpf = :seller_cpf,
                     PERIODO_HEADCOUNT = :period_headcount,
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
            $statement->execute([
                'id' => $id,
                'seller_name' => $sellerName,
                'seller_cpf' => $sellerCpf,
                'period_headcount' => $periodHeadcount,
                'role' => $role,
                'supervisor_name' => $supervisorName,
                'supervisor_cpf' => $supervisorCpf !== '' ? $supervisorCpf : null,
                'coordinator_name' => $coordinatorName,
                'coordinator_cpf' => $coordinatorCpf !== '' ? $coordinatorCpf : null,
                'manager_name' => $managerName,
                'manager_cpf' => $managerCpf !== '' ? $managerCpf : null,
                'base_id' => $baseId,
                'base_group_id' => $baseGroupId,
                'consultant_base_regional' => $consultantBaseRegional,
                'consultant_sector_name' => $consultantSectorName,
                'consultant_sector_type' => $consultantSectorType,
                'territory_manager_name' => $territoryManagerName,
                'territory_manager_cpf' => $territoryManagerCpf !== '' ? $territoryManagerCpf : null,
                'updated_by_user_id' => $userId,
            ]);

            return $id;
        }

        $statement = Database::connection()->prepare(
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
                :period_headcount,
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
        $statement->execute([
            'seller_name' => $sellerName,
            'seller_cpf' => $sellerCpf,
            'period_headcount' => $periodHeadcount,
            'role' => $role,
            'supervisor_name' => $supervisorName,
            'supervisor_cpf' => $supervisorCpf !== '' ? $supervisorCpf : null,
            'coordinator_name' => $coordinatorName,
            'coordinator_cpf' => $coordinatorCpf !== '' ? $coordinatorCpf : null,
            'manager_name' => $managerName,
            'manager_cpf' => $managerCpf !== '' ? $managerCpf : null,
            'base_id' => $baseId,
            'base_group_id' => $baseGroupId,
            'consultant_base_regional' => $consultantBaseRegional,
            'consultant_sector_name' => $consultantSectorName,
            'consultant_sector_type' => $consultantSectorType,
            'territory_manager_name' => $territoryManagerName,
            'territory_manager_cpf' => $territoryManagerCpf !== '' ? $territoryManagerCpf : null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function findBaseByName(string $name): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM hierarchy_bases WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);

        return $statement->fetch() ?: null;
    }

    private function findGroupByName(string $name): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM hierarchy_base_groups WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);

        return $statement->fetch() ?: null;
    }

    private function findSellerByCpf(string $sellerCpf, ?string $periodHeadcount = null): ?array
    {
        $sql = 'SELECT * FROM seller_hierarchies WHERE seller_cpf = :seller_cpf';
        $bindings = ['seller_cpf' => clean_document($sellerCpf)];

        if ($periodHeadcount !== null && preg_match('/^\d{6}$/', $periodHeadcount)) {
            $sql .= ' AND PERIODO_HEADCOUNT = :period_headcount';
            $bindings['period_headcount'] = $periodHeadcount;
        }

        $sql .= ' ORDER BY PERIODO_HEADCOUNT DESC, id DESC LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetch() ?: null;
    }

    private function buildSellerFilters(?string $term = null, array $baseIds = [], array $roles = [], ?string $periodHeadcount = null): array
    {
        $baseSql = ' FROM seller_hierarchies
                     INNER JOIN hierarchy_bases ON hierarchy_bases.id = seller_hierarchies.base_id
                     INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = seller_hierarchies.base_group_id
                     WHERE 1 = 1';
        $filtersSql = '';
        $bindings = [];
        $periodHeadcount = preg_match('/^\d{6}$/', (string) $periodHeadcount)
            ? (string) $periodHeadcount
            : $this->latestHeadcountPeriod();

        if ($periodHeadcount !== null) {
            $filtersSql .= ' AND seller_hierarchies.PERIODO_HEADCOUNT = :filter_period_headcount';
            $bindings['filter_period_headcount'] = $periodHeadcount;
        }

        if ($term !== null && trim($term) !== '') {
            $filtersSql .= ' AND (
                seller_hierarchies.seller_name LIKE :term OR
                seller_hierarchies.seller_cpf LIKE :term OR
                seller_hierarchies.supervisor_name LIKE :term OR
                hierarchy_bases.name LIKE :term OR
                hierarchy_base_groups.name LIKE :term
            )';
            $bindings['term'] = '%' . trim($term) . '%';
        }

        $baseIds = array_values(array_unique(array_filter(array_map('intval', $baseIds), static fn (int $baseId): bool => $baseId > 0)));
        if ($baseIds !== []) {
            $basePlaceholders = [];

            foreach ($baseIds as $index => $baseId) {
                $placeholder = 'filter_base_id_' . $index;
                $basePlaceholders[] = ':' . $placeholder;
                $bindings[$placeholder] = $baseId;
            }

            $filtersSql .= ' AND seller_hierarchies.base_id IN (' . implode(', ', $basePlaceholders) . ')';
        }

        $roles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(normalize_text((string) $role)),
            $roles
        ), static fn (string $role): bool => in_array($role, self::ALLOWED_ROLES, true))));

        if ($roles !== []) {
            $rolePlaceholders = [];

            foreach ($roles as $index => $role) {
                $placeholder = 'filter_role_' . $index;
                $rolePlaceholders[] = ':' . $placeholder;
                $bindings[$placeholder] = $role;
            }

            $filtersSql .= ' AND seller_hierarchies.role IN (' . implode(', ', $rolePlaceholders) . ')';
        }

        return [$baseSql, $filtersSql, $bindings];
    }

    private function findSellerByName(string $sellerName, ?string $role = null, ?string $periodHeadcount = null): ?array
    {
        $sql = 'SELECT * FROM seller_hierarchies WHERE seller_name = :seller_name';
        $bindings = ['seller_name' => $this->normalizeUpperText($sellerName)];

        if ($role !== null && $role !== '') {
            $sql .= ' AND role = :role';
            $bindings['role'] = $role;
        }

        if ($periodHeadcount !== null && preg_match('/^\d{6}$/', $periodHeadcount)) {
            $sql .= ' AND PERIODO_HEADCOUNT = :period_headcount';
            $bindings['period_headcount'] = $periodHeadcount;
        }

        $sql .= ' ORDER BY PERIODO_HEADCOUNT DESC, id ASC LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetch() ?: null;
    }

    private function resolveLinkedCpf(
        string $linkedName,
        string $preferredRole,
        string $periodHeadcount,
        string $sellerName,
        string $sellerCpf,
        ?string $currentLinkedName = null,
        ?string $currentLinkedCpf = null
    ): string {
        $linkedName = $this->normalizeUpperText($linkedName);

        if ($linkedName === '' || $linkedName === '-') {
            return '';
        }

        if ($linkedName === $sellerName) {
            return $sellerCpf;
        }

        $currentLinkedName = $this->normalizeUpperText($currentLinkedName);
        $currentLinkedCpf = clean_document($currentLinkedCpf);

        if ($currentLinkedName !== '' && $currentLinkedName === $linkedName && $currentLinkedCpf !== '') {
            return $currentLinkedCpf;
        }

        $linkedSeller = $this->findSellerByName($linkedName, $preferredRole, $periodHeadcount)
            ?? $this->findSellerByName($linkedName, null, $periodHeadcount)
            ?? $this->findSellerByName($linkedName, $preferredRole)
            ?? $this->findSellerByName($linkedName);

        return $linkedSeller !== null ? clean_document($linkedSeller['seller_cpf'] ?? '') : '';
    }

    private function normalizeUpperText(?string $value): string
    {
        return mb_strtoupper(normalize_text($value));
    }

    private function normalizeNullableUpperText(?string $value): ?string
    {
        $normalizedValue = $this->normalizeUpperText($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
