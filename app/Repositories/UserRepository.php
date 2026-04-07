<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

final class UserRepository
{
    private const ALLOWED_ROLES = ['ADMINISTRADOR', 'BACKOFFICE', 'BACKOFFICE SUPERVISOR'];
    private const ALLOWED_REGIONAL_VIEWS = ['FULL', 'I', 'II', 'PERSONALIZADO'];

    public function findByCpf(string $cpf): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE cpf = :cpf LIMIT 1');
        $statement->execute(['cpf' => clean_document($cpf)]);

        return $this->hydrateUser($statement->fetch() ?: null);
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $this->hydrateUser($statement->fetch() ?: null);
    }

    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => mb_strtolower(normalize_text($email))]);

        return $statement->fetch() ?: null;
    }

    public function touchLastLogin(int $id): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function touchPresence(int $id, string $sessionId): void
    {
        if ($id <= 0 || trim($sessionId) === '') {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE users
             SET last_seen_at = NOW(),
                 current_session_id = :session_id
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'session_id' => $sessionId,
        ]);
    }

    public function clearPresence(int $id, string $sessionId): void
    {
        if ($id <= 0 || trim($sessionId) === '') {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE users
             SET current_session_id = NULL
             WHERE id = :id
               AND current_session_id = :session_id'
        );
        $statement->execute([
            'id' => $id,
            'session_id' => $sessionId,
        ]);
    }

    public function stats(): array
    {
        $connection = Database::connection();

        return [
            'total' => (int) $connection->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'active' => (int) $connection->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
            'inactive' => (int) $connection->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn(),
            'admins' => (int) $connection->query("SELECT COUNT(*) FROM users WHERE role = 'ADMINISTRADOR'")->fetchColumn(),
        ];
    }

    public function all(?string $term = null, ?string $role = null, ?string $regionalView = null): array
    {
        $sql = 'SELECT users.*,
                       COALESCE(scope_counts.scope_count, 0) AS base_group_scope_count
                FROM users
                LEFT JOIN (
                    SELECT user_id, COUNT(*) AS scope_count
                    FROM user_base_group_scopes
                    GROUP BY user_id
                ) AS scope_counts ON scope_counts.user_id = users.id
                WHERE 1 = 1';
        $bindings = [];

        if ($term !== null && trim($term) !== '') {
            $normalizedTerm = mb_strtoupper(normalize_text($term));
            $cleanTerm = clean_document($term);

            $sql .= ' AND (name LIKE :term OR role LIKE :term OR regional_view LIKE :term';
            $bindings['term'] = '%' . $normalizedTerm . '%';

            if ($cleanTerm !== '') {
                $sql .= ' OR cpf LIKE :clean_term';
                $bindings['clean_term'] = '%' . $cleanTerm . '%';
            }

            $sql .= ')';
        }

        $role = strtoupper(normalize_text($role));
        if ($role !== '' && in_array($role, self::ALLOWED_ROLES, true)) {
            $sql .= ' AND role = :filter_role';
            $bindings['filter_role'] = $role;
        }

        $regionalView = strtoupper(normalize_text($regionalView));
        if ($regionalView !== '' && in_array($regionalView, self::ALLOWED_REGIONAL_VIEWS, true)) {
            $sql .= ' AND regional_view = :filter_regional_view';
            $bindings['filter_regional_view'] = $regionalView;
        }

        $sql .= ' ORDER BY users.is_active DESC, users.name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function save(?int $id, array $data): int
    {
        $name = mb_strtoupper(normalize_text($data['name'] ?? ''));
        $email = mb_strtolower(normalize_text($data['email'] ?? ''));
        $cpf = clean_document($data['cpf'] ?? '');
        $role = strtoupper(normalize_text($data['role'] ?? ''));
        $regionalView = strtoupper(normalize_text($data['regional_view'] ?? 'FULL'));
        $baseGroupScopeIds = $this->normalizeBaseGroupScopeIds($data['base_group_scope_ids'] ?? []);
        $isActive = (int) ($data['is_active'] ?? 0) === 1 ? 1 : 0;
        $existingRecord = $id !== null && $id > 0 ? $this->findById($id) : null;

        if ($name === '' || $email === '' || $cpf === '' || $role === '') {
            throw new RuntimeException('Preencha nome, e-mail, CPF e perfil de acesso.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um e-mail válido.');
        }

        if (strlen($cpf) !== 11) {
            throw new RuntimeException('Informe o CPF com 11 números.');
        }

        if (! valid_cpf($cpf)) {
            throw new RuntimeException('CPF inválido.');
        }

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new RuntimeException('Selecione um perfil de acesso válido.');
        }

        $allowsRegionalScope = in_array($role, ['BACKOFFICE', 'BACKOFFICE SUPERVISOR'], true);

        if ($role === 'ADMINISTRADOR' || ! $allowsRegionalScope) {
            $regionalView = 'FULL';
        }

        if (! in_array($regionalView, self::ALLOWED_REGIONAL_VIEWS, true)) {
            throw new RuntimeException('Selecione uma visão regional válida.');
        }

        if ($regionalView === 'PERSONALIZADO') {
            if (! $allowsRegionalScope) {
                throw new RuntimeException('A visão personalizada está disponível apenas para Backoffice e Supervisor Backoffice.');
            }

            if ($baseGroupScopeIds === []) {
                throw new RuntimeException('Selecione ao menos um base grupo para a visão personalizada.');
            }

            $this->assertBaseGroupScopesExist($baseGroupScopeIds);
        } else {
            $baseGroupScopeIds = [];
        }

        $existingUser = $this->findByCpf($cpf);
        if ($existingUser !== null && (int) $existingUser['id'] !== (int) $id) {
            throw new RuntimeException('Já existe um usuário cadastrado com esse CPF.');
        }

        $existingEmailUser = $this->findByEmail($email);
        if ($existingEmailUser !== null && (int) $existingEmailUser['id'] !== (int) $id) {
            throw new RuntimeException('Já existe um usuário cadastrado com esse e-mail.');
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            if ($id !== null && $id > 0) {
                $bindings = [
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                    'cpf' => $cpf,
                    'role' => $role,
                    'regional_view' => $regionalView,
                    'is_active' => $isActive,
                ];
                $updateSql = 'UPDATE users
                     SET name = :name,
                         email = :email,
                         cpf = :cpf,
                         role = :role,
                         regional_view = :regional_view,
                         is_active = :is_active';

                if (
                    $existingRecord !== null
                    && (int) ($existingRecord['must_change_password'] ?? 0) === 1
                    && clean_document((string) ($existingRecord['cpf'] ?? '')) !== $cpf
                ) {
                    $updateSql .= ',
                         password_hash = :password_hash,
                         must_change_password = 1';
                    $bindings['password_hash'] = password_hash($cpf, PASSWORD_DEFAULT);
                }

                $updateSql .= ' WHERE id = :id';

                $statement = $connection->prepare($updateSql);
                $statement->execute($bindings);

                $this->replaceBaseGroupScopes($id, $baseGroupScopeIds);
                $connection->commit();

                return $id;
            }

            $statement = $connection->prepare(
                'INSERT INTO users (name, email, cpf, password_hash, must_change_password, role, regional_view, is_active)
                 VALUES (:name, :email, :cpf, :password_hash, :must_change_password, :role, :regional_view, :is_active)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'cpf' => $cpf,
                'password_hash' => password_hash($cpf, PASSWORD_DEFAULT),
                'must_change_password' => 1,
                'role' => $role,
                'regional_view' => $regionalView,
                'is_active' => $isActive,
            ]);

            $newUserId = (int) $connection->lastInsertId();
            $this->replaceBaseGroupScopes($newUserId, $baseGroupScopeIds);
            $connection->commit();

            return $newUserId;
        } catch (\Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function resetPassword(int $id): void
    {
        $user = $this->findById($id);

        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        $cpf = clean_document($user['cpf'] ?? '');

        if ($cpf === '' || strlen($cpf) !== 11 || ! valid_cpf($cpf)) {
            throw new RuntimeException('Não foi possível redefinir a senha porque o CPF do usuário está inválido.');
        }

        $statement = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 must_change_password = 1
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'password_hash' => password_hash($cpf, PASSWORD_DEFAULT),
        ]);
    }

    public function changePassword(int $id, string $password): void
    {
        $user = $this->findById($id);

        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        $statement = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 must_change_password = 0
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    private function hydrateUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $scopes = $this->baseGroupScopesByUserId((int) ($user['id'] ?? 0));

        $user['base_group_scope_ids'] = array_values(array_map(
            static fn (array $scope): int => (int) ($scope['id'] ?? 0),
            $scopes
        ));
        $user['base_group_scopes'] = $scopes;
        $user['base_group_scope_count'] = count($scopes);

        return $user;
    }

    private function baseGroupScopesByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT hierarchy_base_groups.id,
                    hierarchy_base_groups.name,
                    hierarchy_bases.name AS base_name,
                    hierarchy_bases.REGIONAL AS base_regional
             FROM user_base_group_scopes
             INNER JOIN hierarchy_base_groups ON hierarchy_base_groups.id = user_base_group_scopes.base_group_id
             LEFT JOIN hierarchy_bases ON hierarchy_bases.id = hierarchy_base_groups.base_id
             WHERE user_base_group_scopes.user_id = :user_id
             ORDER BY hierarchy_bases.name ASC, hierarchy_base_groups.name ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    private function normalizeBaseGroupScopeIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $scopeIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $scopeId): int => (int) $scopeId,
            $value
        ), static fn (int $scopeId): bool => $scopeId > 0)));

        sort($scopeIds);

        return $scopeIds;
    }

    private function assertBaseGroupScopesExist(array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $placeholders = [];
        $bindings = [];

        foreach ($scopeIds as $index => $scopeId) {
            $key = 'scope_id_' . $index;
            $placeholders[] = ':' . $key;
            $bindings[$key] = $scopeId;
        }

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM hierarchy_base_groups
             WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($bindings);

        if ((int) $statement->fetchColumn() !== count($scopeIds)) {
            throw new RuntimeException('Uma ou mais bases grupo selecionadas não existem mais. Atualize a tela e tente novamente.');
        }
    }

    private function replaceBaseGroupScopes(int $userId, array $scopeIds): void
    {
        $connection = Database::connection();
        $connection->prepare('DELETE FROM user_base_group_scopes WHERE user_id = :user_id')->execute([
            'user_id' => $userId,
        ]);

        if ($scopeIds === []) {
            return;
        }

        $insertStatement = $connection->prepare(
            'INSERT INTO user_base_group_scopes (user_id, base_group_id)
             VALUES (:user_id, :base_group_id)'
        );

        foreach ($scopeIds as $scopeId) {
            $insertStatement->execute([
                'user_id' => $userId,
                'base_group_id' => $scopeId,
            ]);
        }
    }
}
