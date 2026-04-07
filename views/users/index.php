<?php $showRegionalViewField = in_array(($userForm['role'] ?? 'BACKOFFICE'), ['BACKOFFICE', 'BACKOFFICE SUPERVISOR'], true); ?>
<?php $selectedBaseGroupScopeIds = array_values(array_map('intval', $userForm['base_group_scope_ids'] ?? [])); ?>
<?php $showPersonalizedScopeField = $showRegionalViewField && (($userForm['regional_view'] ?? 'FULL') === 'PERSONALIZADO'); ?>
<?php
$userPanelOpen = (int) ($userForm['id'] ?? 0) > 0
    || trim((string) ($userForm['name'] ?? '')) !== ''
    || trim((string) ($userForm['email'] ?? '')) !== ''
    || trim((string) ($userForm['cpf'] ?? '')) !== ''
    || $selectedBaseGroupScopeIds !== [];
?>

<section class="stats-grid">
    <article class="stat-card">
        <span>Total de usu&aacute;rios</span>
        <strong><?= e((string) $stats['total']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Usu&aacute;rios ativos</span>
        <strong><?= e((string) $stats['active']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Usu&aacute;rios inativos</span>
        <strong><?= e((string) $stats['inactive']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Administradores</span>
        <strong><?= e((string) $stats['admins']) ?></strong>
    </article>
</section>

<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $userPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Uso restrito a Administradores</small>
            <strong>Cadastrar Usu&aacute;rio</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $userPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $userPanelOpen ? '' : 'hidden' ?>>
        <div class="panel-head">
            <p>Cadastre quem pode acessar o CRM, ajuste o perfil de acesso e redefina a senha quando necess&aacute;rio. A senha padr&atilde;o sempre ser&aacute; o CPF do usu&aacute;rio.</p>
        </div>

        <form method="post" action="<?= e(url('users/save')) ?>" class="form-grid audit-form">
            <?= \App\Core\Csrf::input() ?>
            <input type="hidden" name="id" value="<?= e((string) $userForm['id']) ?>">

            <label>
                <span>Nome do usu&aacute;rio</span>
                <input type="text" name="name" value="<?= e($userForm['name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>E-mail</span>
                <input type="email" name="email" value="<?= e($userForm['email']) ?>" required>
            </label>

            <label>
                <span>CPF</span>
                <input type="text" name="cpf" value="<?= e($userForm['cpf']) ?>" required maxlength="11" inputmode="numeric" pattern="\d{11}" data-only-digits data-cpf-input>
                <small class="field-error" data-cpf-error hidden>CPF inv&aacute;lido</small>
            </label>

            <label>
                <span>Perfil de acesso</span>
                <select name="role" required data-user-role-select>
                    <?php foreach (['ADMINISTRADOR', 'BACKOFFICE', 'BACKOFFICE SUPERVISOR'] as $role): ?>
                        <option value="<?= e($role) ?>" <?= ($userForm['role'] ?? 'BACKOFFICE') === $role ? 'selected' : '' ?>>
                            <?= e($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label data-user-regional-field <?= $showRegionalViewField ? '' : 'hidden' ?>>
                <span>Vis&atilde;o regional</span>
                <select name="regional_view" <?= $showRegionalViewField ? 'required' : '' ?>>
                    <?php foreach (['FULL', 'I', 'II', 'PERSONALIZADO'] as $regionalView): ?>
                        <option value="<?= e($regionalView) ?>" <?= ($userForm['regional_view'] ?? 'FULL') === $regionalView ? 'selected' : '' ?>>
                            <?= e($regionalView) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="wide user-scope-panel" data-user-scope-field <?= $showPersonalizedScopeField ? '' : 'hidden' ?>>
                <div class="user-scope-panel-head">
                    <div>
                        <strong>Base grupos permitidos</strong>
                        <small>Selecione os base grupos que este usu&aacute;rio poder&aacute; visualizar na fila de vendas.</small>
                    </div>
                    <span class="user-scope-summary" data-user-scope-summary><?= e((string) count($selectedBaseGroupScopeIds)) ?> selecionado(s)</span>
                </div>

                <label class="user-scope-search">
                    <span>Buscar base grupo</span>
                    <input type="text" placeholder="Buscar por opera&ccedil;&atilde;o ou base grupo" data-user-scope-search>
                </label>

                <div class="user-scope-list" data-user-scope-list>
                    <?php if (($userBaseGroupOptions ?? []) === []): ?>
                        <div class="user-scope-empty">Nenhum base grupo cadastrado.</div>
                    <?php else: ?>
                        <?php foreach (($userBaseGroupOptions ?? []) as $group): ?>
                            <?php
                            $groupId = (int) ($group['id'] ?? 0);
                            $groupName = (string) ($group['name'] ?? '');
                            $baseName = (string) (($group['base_name'] ?? '') !== '' ? $group['base_name'] : 'Sem operação');
                            $searchText = mb_strtolower(normalize_text(trim($baseName . ' ' . $groupName)));
                            ?>
                            <label class="user-scope-option" data-user-scope-option data-search-text="<?= e($searchText) ?>">
                                <input
                                    type="checkbox"
                                    name="base_group_scope_ids[]"
                                    value="<?= e((string) $groupId) ?>"
                                    data-user-scope-checkbox
                                    <?= in_array($groupId, $selectedBaseGroupScopeIds, true) ? 'checked' : '' ?>
                                >
                                <span class="user-scope-option-copy">
                                    <strong><?= e($groupName) ?></strong>
                                    <small><?= e($baseName) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <small class="field-error" data-user-scope-error hidden>Selecione ao menos um base grupo.</small>
            </div>

            <label>
                <span>Status</span>
                <select name="is_active" required>
                    <option value="1" <?= (int) ($userForm['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>ATIVO</option>
                    <option value="0" <?= (int) ($userForm['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>INATIVO</option>
                </select>
            </label>

            <div class="wide form-actions">
                <?php if ($userForm['id']): ?>
                    <a href="<?= e(url('users')) ?>" class="ghost-link">Cancelar edi&ccedil;&atilde;o</a>
                <?php else: ?>
                    <span class="muted">Ao criar um novo usu&aacute;rio, a senha inicial ser&aacute; o CPF informado.</span>
                <?php endif; ?>
                <button type="submit" class="primary-button">
                    <?= $userForm['id'] ? 'Salvar altera&ccedil;&otilde;es' : 'Cadastrar usu&aacute;rio' ?>
                </button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <p class="eyebrow">Consulta</p>
        <h3>Usu&aacute;rios cadastrados</h3>
    </div>

    <form method="get" class="filters users-filters-form">
        <input type="hidden" name="route" value="users">
        <label class="users-filter-search">
            <span>Busca</span>
            <input type="text" name="term" value="<?= e($userTermFilter) ?>" placeholder="Nome, CPF, perfil ou vis&atilde;o regional" data-uppercase>
        </label>

        <label class="users-filter-select">
            <span>Perfil</span>
            <select name="role">
                <option value="">Todos</option>
                <?php foreach (['ADMINISTRADOR', 'BACKOFFICE', 'BACKOFFICE SUPERVISOR'] as $role): ?>
                    <option value="<?= e($role) ?>" <?= ($userRoleFilter ?? '') === $role ? 'selected' : '' ?>>
                        <?= e($role) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="users-filter-select">
            <span>Vis&atilde;o regional</span>
            <select name="regional_view">
                <option value="">Todas</option>
                <?php foreach (['FULL', 'I', 'II', 'PERSONALIZADO'] as $regionalView): ?>
                    <option value="<?= e($regionalView) ?>" <?= ($userRegionalViewFilter ?? '') === $regionalView ? 'selected' : '' ?>>
                        <?= e($regionalView) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="secondary-button">Filtrar</button>
        <a
            href="<?= e(url('users')) ?>"
            class="icon-button"
            data-ui-tooltip="Limpar filtros"
            aria-label="Limpar filtros"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
            </svg>
        </a>
    </form>

    <div class="table-wrap compact-table users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Nome</th>
                <th>Perfil</th>
                <th>Vis&atilde;o regional</th>
                <th>Status</th>
                <th>&Uacute;ltimo acesso</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr>
                    <td colspan="6" class="empty-state">Nenhum usu&aacute;rio encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['name']) ?></td>
                        <td><?= e($user['role']) ?></td>
                        <td><?= e(userRegionalViewLabel($user)) ?></td>
                        <td>
                            <span class="status-pill <?= (int) $user['is_active'] === 1 ? 'status-finalizada' : 'status-inativo' ?>">
                                <?= (int) $user['is_active'] === 1 ? 'ATIVO' : 'INATIVO' ?>
                            </span>
                        </td>
                        <td><?= e(format_datetime_br($user['last_login_at'])) ?></td>
                        <td class="actions">
                            <div class="inline-actions">
                                <a href="<?= e(url('users', ['edit_user' => $user['id']])) ?>" class="ghost-link small-button users-edit-button" data-ui-tooltip="Editar usu&aacute;rio" aria-label="Editar usu&aacute;rio">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M16.86 3.49a2 2 0 0 1 2.83 0l.82.82a2 2 0 0 1 0 2.83l-9.2 9.2a2 2 0 0 1-.91.52l-3.33.83a.75.75 0 0 1-.91-.91l.83-3.33a2 2 0 0 1 .52-.91l9.2-9.2zM15.8 5.61 8.4 13.01l-.47 1.88 1.88-.47 7.4-7.4-1.41-1.41z"></path>
                                    </svg>
                                </a>
                                <form method="post" action="<?= e(url('users/reset-password', ['id' => $user['id']])) ?>">
                                    <?= \App\Core\Csrf::input() ?>
                                    <button type="submit" class="secondary-button small-button users-reset-button">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M7 10V8a5 5 0 0 1 10 0v2h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h1zm2 0h6V8a3 3 0 1 0-6 0v2zm3 4a1.75 1.75 0 0 1 1 3.18V19h-2v-1.82A1.75 1.75 0 0 1 12 14z"></path>
                                        </svg>
                                        <span>Resetar senha</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
