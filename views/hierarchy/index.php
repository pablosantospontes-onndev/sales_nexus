<section class="stats-grid hierarchy-stats-grid">
    <article class="stat-card">
        <span>Opera&ccedil;&otilde;es</span>
        <strong><?= e((string) $stats['bases']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Base grupos</span>
        <strong><?= e((string) $stats['groups']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Gerentes</span>
        <strong><?= e((string) ($stats['managers'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span>Coordenadores</span>
        <strong><?= e((string) ($stats['coordinators'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span>Supervisores</span>
        <strong><?= e((string) ($stats['supervisors'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span>Vendedores</span>
        <strong><?= e((string) $stats['sellers']) ?></strong>
    </article>
</section>

<?php
$baseGroupPanelOpen = (bool) ($manageBasesOpen ?? false);
$activeBaseId = (int) ($manageBasesSelectedBaseId ?? 0);
$newGroupBaseId = (int) ($manageBasesNewGroupBaseId ?? 0);
$baseGroupsByBase = [];

foreach ($groups as $group) {
    $baseId = (int) ($group['base_id'] ?? 0);

    if ($baseId <= 0) {
        continue;
    }

    $baseGroupsByBase[$baseId][] = $group;
}
?>

<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $baseGroupPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Opera&ccedil;&otilde;es</small>
            <strong>Cadastrar opera&ccedil;&otilde;es/base</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $baseGroupPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $baseGroupPanelOpen ? '' : 'hidden' ?>>
        <section class="operations-manager">
            <div class="operations-toolbar">
                <div class="operations-toolbar-copy">
                    <span class="eyebrow">Opera&ccedil;&otilde;es</span>
                    <h4><?= (int) ($baseForm['id'] ?? 0) > 0 ? 'Editar opera&ccedil;&atilde;o/base' : 'Nova opera&ccedil;&atilde;o/base' ?></h4>
                    <p class="muted">Selecione uma opera&ccedil;&atilde;o para abrir seus base grupos, editar nomes ou adicionar novos grupos de forma organizada.</p>
                </div>

                <form method="post" action="<?= e(url('hierarchy/base/save')) ?>" class="operations-toolbar-form">
                    <?= \App\Core\Csrf::input() ?>
                    <input type="hidden" name="id" value="<?= e((string) ($baseForm['id'] ?? '')) ?>">

                    <label>
                        <span>Opera&ccedil;&atilde;o / base nome</span>
                        <input type="text" name="name" value="<?= e($baseForm['name'] ?? '') ?>" required data-uppercase>
                    </label>

                    <div class="operations-toolbar-regional compact-choice-filter" data-hierarchy-regional-filter>
                        <span>Regional</span>
                        <button type="button" class="compact-choice-trigger" data-hierarchy-regional-trigger aria-expanded="false">
                            <span data-hierarchy-regional-summary><?= e(($baseForm['regional'] ?? 'I') === 'II' ? 'II' : 'I') ?></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </button>

                        <div class="compact-choice-dropdown" data-hierarchy-regional-dropdown hidden>
                            <?php foreach (['I', 'II'] as $regional): ?>
                                <label class="compact-choice-option">
                                    <input type="radio" name="regional" value="<?= e($regional) ?>" <?= ($baseForm['regional'] ?? 'I') === $regional ? 'checked' : '' ?> data-hierarchy-regional-radio>
                                    <span><?= e($regional) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="operations-toolbar-actions">
                        <?php if ((int) ($baseForm['id'] ?? 0) > 0): ?>
                            <a href="<?= e(url('hierarchy', ['manage_bases' => 1])) ?>" class="ghost-link small-button">Cancelar</a>
                        <?php endif; ?>

                        <button type="submit" class="primary-button small-button">
                            <?= (int) ($baseForm['id'] ?? 0) > 0 ? 'Salvar opera&ccedil;&atilde;o' : 'Adicionar opera&ccedil;&atilde;o' ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="operations-list" data-operations-list>
                <?php if ($bases === []): ?>
                    <div class="empty-state operations-empty-state">Nenhuma opera&ccedil;&atilde;o cadastrada ainda.</div>
                <?php else: ?>
                    <?php foreach ($bases as $base): ?>
                        <?php
                        $baseId = (int) ($base['id'] ?? 0);
                        $baseGroups = $baseGroupsByBase[$baseId] ?? [];
                        $isOpen = $activeBaseId === $baseId;
                        $showGroupForm = $newGroupBaseId === $baseId || (int) ($groupForm['base_id'] ?? 0) === $baseId;
                        ?>
                        <article class="operation-item <?= $isOpen ? 'is-open' : '' ?>" data-operation-item>
                            <div class="operation-item-head">
                                <button
                                    type="button"
                                    class="operation-item-toggle"
                                    data-operation-toggle
                                    aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                                >
                                    <span class="operation-item-main">
                                        <strong><?= e($base['name']) ?></strong>
                                        <small>
                                            Regional <?= e($base['REGIONAL'] ?: '-') ?>
                                            <span>&bull;</span>
                                            <?= e((string) count($baseGroups)) ?> base grupo<?= count($baseGroups) === 1 ? '' : 's' ?>
                                        </small>
                                    </span>
                                    <span class="operation-item-chevron" aria-hidden="true"><?= $isOpen ? '&minus;' : '+' ?></span>
                                </button>

                                <div class="operation-item-actions">
                                    <a
                                        href="<?= e(url('hierarchy', ['manage_bases' => 1, 'open_base' => $baseId, 'new_group_base' => $baseId])) ?>"
                                        class="operation-add-group-button"
                                        data-ui-tooltip="Adicionar base grupo"
                                        aria-label="Adicionar base grupo"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                        </svg>
                                        <span>Novo base grupo</span>
                                    </a>

                                    <a
                                        href="<?= e(url('hierarchy', ['manage_bases' => 1, 'open_base' => $baseId, 'edit_base' => $baseId])) ?>"
                                        class="operation-icon-button"
                                        data-ui-tooltip="Editar opera&ccedil;&atilde;o"
                                        aria-label="Editar opera&ccedil;&atilde;o"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M16.86 3.49a2 2 0 0 1 2.83 0l.82.82a2 2 0 0 1 0 2.83l-9.2 9.2a2 2 0 0 1-.91.52l-3.33.83a.75.75 0 0 1-.91-.91l.83-3.33a2 2 0 0 1 .52-.91l9.2-9.2zM15.8 5.61 8.4 13.01l-.47 1.88 1.88-.47 7.4-7.4-1.41-1.41z"></path>
                                        </svg>
                                    </a>

                                    <form
                                        method="post"
                                        action="<?= e(url('hierarchy/base/delete', ['id' => $baseId])) ?>"
                                        data-hierarchy-delete-form
                                        data-delete-label="opera&ccedil;&atilde;o/base"
                                        data-delete-name="<?= e($base['name']) ?>"
                                    >
                                        <?= \App\Core\Csrf::input() ?>
                                        <button
                                            type="submit"
                                            class="operation-icon-button is-danger"
                                            data-ui-tooltip="Excluir opera&ccedil;&atilde;o"
                                            aria-label="Excluir opera&ccedil;&atilde;o"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 7h2v7h-2v-7zm4 0h2v7h-2v-7zM7 10h2v7H7v-7zm1 10h8a2 2 0 0 0 2-2V7H6v11a2 2 0 0 0 2 2z"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="operation-item-body" data-operation-body <?= $isOpen ? '' : 'hidden' ?>>
                                <?php if ($showGroupForm): ?>
                                    <form method="post" action="<?= e(url('hierarchy/group/save')) ?>" class="operation-group-form">
                                        <?= \App\Core\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= e((string) ($groupForm['id'] ?? '')) ?>">
                                        <input type="hidden" name="base_id" value="<?= e((string) $baseId) ?>">

                                        <div class="operation-group-form-head">
                                            <div>
                                                <span class="eyebrow">Base grupo</span>
                                                <strong><?= (int) ($groupForm['id'] ?? 0) > 0 ? 'Editar base grupo' : 'Novo base grupo' ?></strong>
                                            </div>
                                            <a href="<?= e(url('hierarchy', ['manage_bases' => 1, 'open_base' => $baseId])) ?>" class="ghost-link small-button">Cancelar</a>
                                        </div>

                                        <label>
                                            <span>Nome do base grupo</span>
                                            <input type="text" name="name" value="<?= $showGroupForm ? e($groupForm['name'] ?? '') : '' ?>" required data-uppercase>
                                        </label>

                                        <button type="submit" class="primary-button small-button">
                                            <?= (int) ($groupForm['id'] ?? 0) > 0 ? 'Salvar base grupo' : 'Adicionar base grupo' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <div class="operation-group-list">
                                    <?php if ($baseGroups === []): ?>
                                        <div class="empty-state operation-group-empty-state">Nenhum base grupo vinculado a esta opera&ccedil;&atilde;o.</div>
                                    <?php else: ?>
                                        <?php foreach ($baseGroups as $group): ?>
                                            <div class="operation-group-row">
                                                <div class="operation-group-content">
                                                    <strong><?= e($group['name']) ?></strong>
                                                    <small>Base grupo</small>
                                                </div>

                                                <div class="operation-group-actions">
                                                    <a
                                                        href="<?= e(url('hierarchy', ['manage_bases' => 1, 'open_base' => $baseId, 'edit_group' => $group['id']])) ?>"
                                                        class="operation-icon-button"
                                                        data-ui-tooltip="Editar base grupo"
                                                        aria-label="Editar base grupo"
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M16.86 3.49a2 2 0 0 1 2.83 0l.82.82a2 2 0 0 1 0 2.83l-9.2 9.2a2 2 0 0 1-.91.52l-3.33.83a.75.75 0 0 1-.91-.91l.83-3.33a2 2 0 0 1 .52-.91l9.2-9.2zM15.8 5.61 8.4 13.01l-.47 1.88 1.88-.47 7.4-7.4-1.41-1.41z"></path>
                                                        </svg>
                                                    </a>

                                                    <form
                                                        method="post"
                                                        action="<?= e(url('hierarchy/group/delete', ['id' => $group['id']])) ?>"
                                                        data-hierarchy-delete-form
                                                        data-delete-label="base grupo"
                                                        data-delete-name="<?= e($group['name']) ?>"
                                                    >
                                                        <?= \App\Core\Csrf::input() ?>
                                                        <button
                                                            type="submit"
                                                            class="operation-icon-button is-danger"
                                                            data-ui-tooltip="Excluir base grupo"
                                                            aria-label="Excluir base grupo"
                                                        >
                                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 7h2v7h-2v-7zm4 0h2v7h-2v-7zM7 10h2v7H7v-7zm1 10h8a2 2 0 0 0 2-2V7H6v11a2 2 0 0 0 2 2z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>

<?php $sellerPanelOpen = (int) ($sellerForm['id'] ?? 0) > 0; ?>
<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $sellerPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Vendedores</small>
            <strong><?= $sellerForm['id'] ? 'Editar hierarquia completa do vendedor' : 'Cadastrar Hierarquia completa do vendedor' ?></strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $sellerPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $sellerPanelOpen ? '' : 'hidden' ?>>
        <p class="muted">Esse cadastro vai abastecer automaticamente supervisor, coordenador, gerente, base nome e base grupo na venda a partir do CPF do vendedor.</p>

        <?php if ($bases === [] || $groups === []): ?>
            <div class="flash flash-error">Cadastre primeiro ao menos uma opera&ccedil;&atilde;o/base e um base grupo antes de salvar vendedores.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('hierarchy/seller/save')) ?>" class="form-grid audit-form">
            <?= \App\Core\Csrf::input() ?>
            <input type="hidden" name="id" value="<?= e((string) $sellerForm['id']) ?>">

            <label>
                <span>Nome do vendedor</span>
                <input type="text" name="seller_name" value="<?= e($sellerForm['seller_name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>CPF do vendedor</span>
                <input type="text" name="seller_cpf" value="<?= e($sellerForm['seller_cpf']) ?>" required maxlength="11" inputmode="numeric" pattern="\d{11}" data-only-digits data-cpf-input>
                <small class="field-error" data-cpf-error hidden>CPF inv&aacute;lido</small>
            </label>

            <label>
                <span>Per&iacute;odo headcount</span>
                <input type="text" name="period_headcount" value="<?= e($sellerForm['period_headcount'] ?? '202603') ?>" required maxlength="6" inputmode="numeric" pattern="\d{6}" data-only-digits>
            </label>

            <label>
                <span>Tipo hier&aacute;rquico</span>
                <select name="role" required>
                    <?php foreach (['CONSULTOR', 'SUPERVISOR', 'COORDENADOR', 'GERENTE'] as $role): ?>
                        <option value="<?= e($role) ?>" <?= ($sellerForm['role'] ?? 'CONSULTOR') === $role ? 'selected' : '' ?>>
                            <?= e($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Opera&ccedil;&atilde;o / base nome</span>
                <select name="base_id" required data-hierarchy-base-select>
                    <option value="">Selecione</option>
                    <?php foreach ($bases as $base): ?>
                        <option value="<?= e((string) $base['id']) ?>" <?= (int) $sellerForm['base_id'] === (int) $base['id'] ? 'selected' : '' ?>>
                            <?= e($base['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Base grupo</span>
                <select name="base_group_id" required data-hierarchy-group-select data-selected-group-id="<?= e((string) $sellerForm['base_group_id']) ?>">
                    <option value="">Selecione uma base primeiro</option>
                </select>
            </label>

            <label>
                <span>Supervisor</span>
                <input type="text" name="supervisor_name" value="<?= e($sellerForm['supervisor_name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>Coordenador</span>
                <input type="text" name="coordinator_name" value="<?= e($sellerForm['coordinator_name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>Gerente</span>
                <input type="text" name="manager_name" value="<?= e($sellerForm['manager_name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>Consultor base regional</span>
                <input type="text" name="consultant_base_regional" value="<?= e($sellerForm['consultant_base_regional'] ?? '') ?>" data-uppercase>
            </label>

            <label>
                <span>Consultor setor nome</span>
                <input type="text" name="consultant_sector_name" value="<?= e($sellerForm['consultant_sector_name'] ?? '') ?>" data-uppercase>
            </label>

            <label>
                <span>Consultor setor tipo</span>
                <input type="text" name="consultant_sector_type" value="<?= e($sellerForm['consultant_sector_type'] ?? '') ?>" data-uppercase>
            </label>

            <label>
                <span>Gerente territ&oacute;rio nome</span>
                <input type="text" name="territory_manager_name" value="<?= e($sellerForm['territory_manager_name'] ?? '') ?>" data-uppercase>
            </label>

            <div class="wide form-actions">
                <?php if ($sellerForm['id']): ?>
                    <a href="<?= e(url('hierarchy')) ?>" class="ghost-link">Cancelar edi&ccedil;&atilde;o</a>
                <?php else: ?>
                    <span class="muted">Use o CPF + Per&iacute;odo headcount como chave da hierarquia.</span>
                <?php endif; ?>
                <button type="submit" class="primary-button" <?= $bases === [] || $groups === [] ? 'disabled' : '' ?>>
                    <?= $sellerForm['id'] ? 'Salvar altera&ccedil;&otilde;es' : 'Cadastrar vendedor' ?>
                </button>
            </div>
        </form>
    </div>
</section>

<script id="hierarchy-groups-data" type="application/json"><?= json_encode($groupsByBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php $headcountImportPanelOpen = (bool) ($headcountImportOpen ?? false); ?>
<?php if (! empty($activeHeadcountEdit)): ?>
    <section class="panel hierarchy-edit-alert">
        <div class="hierarchy-edit-alert-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </div>

        <div class="hierarchy-edit-alert-content">
            <p class="eyebrow">Headcount em edi&ccedil;&atilde;o</p>
            <h3><?= e((string) ($activeHeadcountEdit['downloaded_by_name'] ?? 'Usu&aacute;rio n&atilde;o identificado')) ?> est&aacute; editando o headcount</h3>
            <p class="muted">
                Download realizado em <?= e(format_datetime_br((string) ($activeHeadcountEdit['downloaded_at'] ?? null))) ?>
                <?php if (! empty($activeHeadcountEdit['period_headcount'])): ?>
                    <span>&bull;</span>
                    Per&iacute;odo <?= e((string) $activeHeadcountEdit['period_headcount']) ?>
                <?php endif; ?>
            </p>
        </div>
    </section>
<?php endif; ?>

<section class="panel dashboard-filters-panel" data-collapsible-panel id="headcount-upload">
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $headcountImportPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Headcount</small>
            <strong>Importar planilha XLSX</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $headcountImportPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $headcountImportPanelOpen ? '' : 'hidden' ?>>
        <form method="post" action="<?= e(url('hierarchy/import')) ?>" enctype="multipart/form-data" class="product-export-form hierarchy-import-form">
            <?= \App\Core\Csrf::input() ?>

            <label class="hierarchy-upload-field" data-file-field>
                <span>Planilha de hierarquia</span>
                <span class="upload-field-control" data-file-control>
                    <input type="file" name="hierarchy_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-file-input data-empty-label="Nenhum arquivo escolhido">
                    <span class="upload-field-button" aria-hidden="true">Escolher arquivo</span>
                    <span class="upload-field-name" data-file-name>Nenhum arquivo escolhido</span>
                </span>
            </label>

            <button type="submit" class="secondary-button hierarchy-import-submit">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Importar XLSX</span>
            </button>
        </form>

        <p class="muted hierarchy-import-note">A atualiza&ccedil;&atilde;o respeita a chave <strong>CPF + PER&Iacute;ODO HEADCOUNT</strong>, preservando hist&oacute;rico de meses anteriores.</p>
    </div>
</section>

<section class="panel" id="hierarchy-seller-panel" data-hierarchy-seller-panel>
    <div class="panel-head">
        <p class="eyebrow">Consulta</p>
        <h3>Hierarquias cadastradas</h3>
        <p class="muted">
            Exibindo <?= e((string) $sellerTotal) ?> registros, 50 por p&aacute;gina.
            <?php if (! empty($latestHeadcountPeriod)): ?>
                <span>Per&iacute;odo exibido: <?= e((string) $latestHeadcountPeriod) ?></span>
            <?php endif; ?>
        </p>
    </div>

    <?php
    $selectedSellerBaseLabels = array_values(array_filter(array_map(
        static function (array $base) use ($sellerBaseFilter): ?string {
            return in_array((int) ($base['id'] ?? 0), $sellerBaseFilter ?? [], true)
                ? (string) ($base['name'] ?? '')
                : null;
        },
        $bases
    )));
    $sellerBaseSummaryLabel = $selectedSellerBaseLabels === []
        ? 'Todas'
        : (count($selectedSellerBaseLabels) === 1
            ? $selectedSellerBaseLabels[0]
            : count($selectedSellerBaseLabels) . ' selecionadas');
    $sellerRoleSummaryLabel = $sellerRoleFilter === []
        ? 'Todos'
        : (count($sellerRoleFilter) === 1
            ? $sellerRoleFilter[0]
            : count($sellerRoleFilter) . ' selecionados');
    ?>

    <form method="get" class="filters products-filters-form hierarchy-filters-form" data-hierarchy-seller-filters-form>
        <input type="hidden" name="route" value="hierarchy">

        <label class="hierarchy-filter-search">
            <span>Busca</span>
            <input type="text" name="term" value="<?= e($sellerTermFilter) ?>" placeholder="Vendedor, CPF, supervisor, base...">
        </label>

        <div class="queue-status-filter hierarchy-filter-multi hierarchy-base-filter" data-hierarchy-base-filter>
            <span>Opera&ccedil;&atilde;o</span>
            <button type="button" class="queue-status-trigger" data-hierarchy-base-trigger aria-expanded="false">
                <span data-hierarchy-base-summary><?= e($sellerBaseSummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-hierarchy-base-dropdown hidden>
                <?php foreach ($bases as $base): ?>
                    <label class="queue-status-option">
                        <input type="checkbox" name="base_id[]" value="<?= e((string) $base['id']) ?>" <?= in_array((int) ($base['id'] ?? 0), $sellerBaseFilter ?? [], true) ? 'checked' : '' ?> data-hierarchy-base-checkbox>
                        <span><?= e($base['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="queue-status-filter hierarchy-filter-multi hierarchy-role-filter" data-hierarchy-role-filter>
            <span>Tipo</span>
            <button type="button" class="queue-status-trigger" data-hierarchy-role-trigger aria-expanded="false">
                <span data-hierarchy-role-summary><?= e($sellerRoleSummaryLabel) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            <div class="queue-status-dropdown" data-hierarchy-role-dropdown hidden>
                <?php foreach ($hierarchyRoles as $role): ?>
                    <label class="queue-status-option">
                        <input type="checkbox" name="role[]" value="<?= e($role) ?>" <?= in_array($role, $sellerRoleFilter ?? [], true) ? 'checked' : '' ?> data-hierarchy-role-checkbox>
                        <span><?= e($role) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="secondary-button">Filtrar</button>
        <a
            href="<?= e(url('hierarchy')) ?>"
            class="icon-button"
            data-hierarchy-seller-panel-link
            data-ui-tooltip="Limpar filtros"
            aria-label="Limpar filtros"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
            </svg>
        </a>
        <button
            type="button"
            class="success-button product-download-button"
            data-open-hierarchy-export-modal
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8l-4-6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                <path d="M14 2v6h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                <path d="M8.65 11.1h1.7l1.05 1.84 1.08-1.84h1.67l-1.86 2.88 1.97 2.92h-1.74l-1.16-1.95-1.16 1.95H8.48l1.96-2.9-1.79-2.9z" fill="currentColor"></path>
            </svg>
            <span>Download</span>
        </button>
    </form>

    <div class="table-wrap compact-table hierarchy-table-wrap">
        <table class="hierarchy-table">
            <thead>
            <tr>
                <th>Vendedor</th>
                <th>CPF</th>
                <th>Tipo</th>
                <th>Supervisor</th>
                <th>Coordenador</th>
                <th>Gerente</th>
                <th>Opera&ccedil;&atilde;o</th>
                <th>Base grupo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($sellers === []): ?>
                <tr>
                    <td colspan="9" class="empty-state">Nenhuma hierarquia cadastrada ainda.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td><?= e($seller['seller_name']) ?></td>
                        <td><?= e($seller['seller_cpf']) ?></td>
                        <td><?= e($seller['role']) ?></td>
                        <td><?= e($seller['supervisor_name']) ?></td>
                        <td><?= e($seller['coordinator_name']) ?></td>
                        <td><?= e($seller['manager_name']) ?></td>
                        <td><?= e($seller['base_name']) ?></td>
                        <td><?= e($seller['base_group_name']) ?></td>
                        <td class="actions"><a href="<?= e(url('hierarchy', ['edit_seller' => $seller['id']])) ?>" class="ghost-link small-button product-edit-link">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($sellerTotalPages > 1): ?>
        <?php
        $baseParams = [
            'term' => $sellerTermFilter !== '' ? $sellerTermFilter : null,
            'base_id' => $sellerBaseFilter !== [] ? $sellerBaseFilter : null,
            'role' => $sellerRoleFilter !== [] ? $sellerRoleFilter : null,
        ];
        $startPage = max(1, $sellerCurrentPage - 2);
        $endPage = min($sellerTotalPages, $sellerCurrentPage + 2);
        ?>
        <div class="pagination">
            <span class="muted">P&aacute;gina <?= e((string) $sellerCurrentPage) ?> de <?= e((string) $sellerTotalPages) ?></span>

            <div class="pagination-links">
                <?php if ($sellerCurrentPage > 1): ?>
                    <a href="<?= e(url('hierarchy', $baseParams + ['page' => $sellerCurrentPage - 1])) ?>" class="ghost-link small-button" data-hierarchy-seller-panel-link>Anterior</a>
                <?php endif; ?>

                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <a
                        href="<?= e(url('hierarchy', $baseParams + ['page' => $page])) ?>"
                        class="<?= $page === $sellerCurrentPage ? 'primary-button small-button' : 'ghost-link small-button' ?>"
                        data-hierarchy-seller-panel-link
                    >
                        <?= e((string) $page) ?>
                    </a>
                <?php endfor; ?>

                <?php if ($sellerCurrentPage < $sellerTotalPages): ?>
                    <a href="<?= e(url('hierarchy', $baseParams + ['page' => $sellerCurrentPage + 1])) ?>" class="ghost-link small-button" data-hierarchy-seller-panel-link>Pr&oacute;xima</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php $headcountExportPanelOpen = (bool) ($headcountExportOpen ?? false); ?>
<div class="modal-shell" data-hierarchy-export-modal <?= $headcountExportPanelOpen ? '' : 'hidden' ?>>
    <div class="modal-backdrop" data-close-hierarchy-export-modal></div>
    <div class="modal-card hierarchy-export-modal-card" role="dialog" aria-modal="true" aria-labelledby="hierarchy-export-title">
        <div class="section-header">
            <div>
                <p class="eyebrow">Download XLSX</p>
                <h4 id="hierarchy-export-title">Selecionar per&iacute;odo headcount</h4>
            </div>
            <button type="button" class="ghost-button small-button" data-close-hierarchy-export-modal>Fechar</button>
        </div>

        <form method="get" action="<?= e(url('hierarchy/export')) ?>" class="form-grid">
            <input type="hidden" name="route" value="hierarchy/export">
            <?php if ($sellerTermFilter !== ''): ?>
                <input type="hidden" name="term" value="<?= e($sellerTermFilter) ?>">
            <?php endif; ?>
            <?php if (($sellerBaseFilter ?? []) !== []): ?>
                <?php foreach ($sellerBaseFilter as $selectedBaseId): ?>
                    <input type="hidden" name="base_id[]" value="<?= e((string) $selectedBaseId) ?>">
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (($sellerRoleFilter ?? []) !== []): ?>
                <?php foreach ($sellerRoleFilter as $selectedRole): ?>
                    <input type="hidden" name="role[]" value="<?= e($selectedRole) ?>">
                <?php endforeach; ?>
            <?php endif; ?>

            <label>
                <span>Per&iacute;odo headcount</span>
                <select name="period_headcount" required>
                    <option value="">Selecione</option>
                    <?php foreach ($headcountPeriods as $periodHeadcount): ?>
                        <option value="<?= e((string) $periodHeadcount) ?>" <?= (string) $periodHeadcount === (string) ($latestHeadcountPeriod ?? '') ? 'selected' : '' ?>>
                            <?= e((string) $periodHeadcount) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-actions">
                <span class="muted">O download respeita os filtros atuais e o per&iacute;odo selecionado.</span>
                <button type="submit" class="success-button product-download-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8l-4-6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                        <path d="M14 2v6h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                        <path d="M8.65 11.1h1.7l1.05 1.84 1.08-1.84h1.67l-1.86 2.88 1.97 2.92h-1.74l-1.16-1.95-1.16 1.95H8.48l1.96-2.9-1.79-2.9z" fill="currentColor"></path>
                    </svg>
                    <span>Baixar XLSX</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-shell" data-hierarchy-delete-modal hidden>
    <div class="modal-backdrop" data-close-hierarchy-delete-modal></div>
    <div class="modal-card hierarchy-delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="hierarchy-delete-title">
        <div class="section-header">
            <div>
                <p class="eyebrow">Confirma&ccedil;&atilde;o</p>
                <h4 id="hierarchy-delete-title" data-hierarchy-delete-title>Excluir item</h4>
            </div>
            <button type="button" class="ghost-button small-button" data-close-hierarchy-delete-modal>Fechar</button>
        </div>

        <p class="hierarchy-delete-lead" data-hierarchy-delete-description>
            Revise o item selecionado antes de continuar. Voc&ecirc; ainda poder&aacute; cancelar durante a contagem regressiva.
        </p>

        <div class="hierarchy-delete-highlight">
            <span class="hierarchy-delete-highlight-label">Item selecionado</span>
            <strong class="hierarchy-delete-highlight-name" data-hierarchy-delete-subject>-</strong>
        </div>

        <div class="hierarchy-delete-warning">
            <strong>Aten&ccedil;&atilde;o:</strong>
            ap&oacute;s confirmar, o sistema aguardar&aacute; 10 segundos antes de enviar a exclus&atilde;o.
        </div>

        <div class="hierarchy-delete-countdown" data-hierarchy-delete-countdown-panel hidden>
            <div class="hierarchy-delete-countdown-row">
                <strong data-hierarchy-delete-countdown>Exclus&atilde;o em 10s</strong>
                <span>Voc&ecirc; ainda pode cancelar.</span>
            </div>
            <div class="hierarchy-delete-progress">
                <span class="hierarchy-delete-progress-bar" data-hierarchy-delete-progress></span>
            </div>
        </div>

        <div class="hierarchy-delete-actions">
            <button type="button" class="ghost-button" data-close-hierarchy-delete-modal>Cancelar</button>
            <button type="button" class="danger-button" data-hierarchy-delete-confirm>Confirmar exclus&atilde;o</button>
        </div>
    </div>
</div>
