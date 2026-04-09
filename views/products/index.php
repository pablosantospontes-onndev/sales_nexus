<section class="stats-grid">
    <article class="stat-card">
        <span>Total de produtos</span>
        <strong><?= e((string) $stats['total']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Produtos ativos</span>
        <strong><?= e((string) $stats['active']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Ativos B2B</span>
        <strong><?= e((string) $stats['b2b']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Ativos B2C</span>
        <strong><?= e((string) $stats['b2c']) ?></strong>
    </article>
</section>

<?php $selectedExportPeriod = $productExportPeriodFilter !== '' ? $productExportPeriodFilter : ($productPeriods[0] ?? ''); ?>
<?php $productPanelOpen = (int) ($productForm['id'] ?? 0) > 0; ?>
<?php $productImportPanelOpen = ! empty($productImportOpen); ?>
<?php $productDuplicateAvailable = ! empty($productDuplicateSourcePeriod); ?>
<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $productImportPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Importa&ccedil;&atilde;o em lote</small>
            <strong>Atualizar produtos via XLSX</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $productImportPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $productImportPanelOpen ? '' : 'hidden' ?>>
        <form method="post" action="<?= e(url('products/import')) ?>" enctype="multipart/form-data" class="product-export-form product-import-form">
            <?= \App\Core\Csrf::input() ?>

            <label class="product-upload-field" data-file-field>
                <span>Planilha de produtos</span>
                <span class="upload-field-control" data-file-control>
                    <input type="file" name="products_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-file-input data-empty-label="Nenhum arquivo escolhido">
                    <span class="upload-field-button" aria-hidden="true">Escolher arquivo</span>
                    <span class="upload-field-name" data-file-name>Nenhum arquivo escolhido</span>
                </span>
            </label>

            <button type="submit" class="secondary-button product-import-submit">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Importar XLSX</span>
            </button>
        </form>

        <p class="muted product-import-note">
            Use o mesmo arquivo exportado pelo sistema.
        </p>
    </div>
</section>

<section class="panel dashboard-filters-panel" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="<?= $productPanelOpen ? 'true' : 'false' ?>"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Cat&aacute;logo</small>
            <strong><?= $productForm['id'] ? 'Editar produto' : 'Cadastrar novo produto' ?></strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon><?= $productPanelOpen ? '&minus;' : '+' ?></span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body <?= $productPanelOpen ? '' : 'hidden' ?>>
        <p class="muted">Edite o cat&aacute;logo de produtos. Se quiser, depois voc&ecirc; pode recalcular as vendas finalizadas apenas na faixa de datas desejada.</p>

        <?php if ($productPeriods !== []): ?>
            <div class="inner-card product-export-card">
                <div class="section-header compact-section-header">
                    <div>
                        <p class="eyebrow">Exporta&ccedil;&atilde;o</p>
                        <h4>Baixar tabela de produtos em XLSX</h4>
                    </div>
                </div>

                <form method="get" action="<?= e(url('products/export')) ?>" class="product-export-form">
                    <input type="hidden" name="route" value="products/export">
                    <label>
                        <span>PER&Iacute;ODO</span>
                        <select name="period" required>
                            <?php foreach ($productPeriods as $period): ?>
                                <option value="<?= e($period) ?>" <?= $selectedExportPeriod === $period ? 'selected' : '' ?>>
                                    <?= e($period) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="product-export-actions">
                        <button type="submit" class="success-button product-download-button">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8l-4-6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                                <path d="M14 2v6h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                                <path d="M8.65 11.1h1.7l1.05 1.84 1.08-1.84h1.67l-1.86 2.88 1.97 2.92h-1.74l-1.16-1.95-1.16 1.95H8.48l1.96-2.9-1.79-2.9z" fill="currentColor"></path>
                            </svg>
                            <span>Download</span>
                        </button>

                        <button
                            type="button"
                            class="secondary-button product-duplicate-button"
                            data-open-product-duplicate-modal
                            <?= $productDuplicateAvailable ? '' : 'disabled' ?>
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 7V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                <rect x="3" y="8" width="13" height="13" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="1.8"></rect>
                            </svg>
                            <span>Duplicar cat&aacute;logo</span>
                        </button>
                    </div>
                </form>

                <div class="modal-shell" data-product-duplicate-modal <?= ! empty($productDuplicateOpen) ? '' : 'hidden' ?>>
                    <div class="modal-backdrop" data-close-product-duplicate-modal></div>
                    <div class="modal-card product-duplicate-modal-card" role="dialog" aria-modal="true" aria-labelledby="product-duplicate-title">
                        <div class="section-header compact-section-header">
                            <div>
                                <p class="eyebrow">Duplicar cat&aacute;logo</p>
                                <h4 id="product-duplicate-title">Duplicar cat&aacute;logo de produtos</h4>
                            </div>
                            <button type="button" class="ghost-button small-button" data-close-product-duplicate-modal>Fechar</button>
                        </div>

                        <?php if ($productDuplicateAvailable): ?>
                            <p class="muted product-duplicate-lead">
                                Uma nova base do cat&aacute;logo ser&aacute; criada para o m&ecirc;s atual, preservando integralmente o per&iacute;odo anterior.
                            </p>

                            <div class="product-duplicate-summary">
                                <article class="product-duplicate-period-card">
                                    <small>Origem</small>
                                    <strong><?= e($productDuplicateSourcePeriod) ?></strong>
                                    <span>Cat&aacute;logo do per&iacute;odo anterior</span>
                                </article>

                                <span class="product-duplicate-arrow" aria-hidden="true">&rarr;</span>

                                <article class="product-duplicate-period-card is-target">
                                    <small>Destino</small>
                                    <strong><?= e($productDuplicateCurrentPeriod) ?></strong>
                                    <span>Nova base para o m&ecirc;s atual</span>
                                </article>
                            </div>

                            <form method="post" action="<?= e(url('products/duplicate')) ?>" class="product-duplicate-form">
                                <?= \App\Core\Csrf::input() ?>

                                <div class="product-duplicate-footer">
                                    <div class="product-duplicate-note">
                                        <strong>O que acontece?</strong>
                                        <span>Todos os produtos do per&iacute;odo <?= e($productDuplicateSourcePeriod) ?> ser&atilde;o copiados para <?= e($productDuplicateCurrentPeriod) ?> como novos registros.</span>
                                    </div>
                                    <div class="form-actions-right">
                                        <button type="button" class="ghost-button" data-close-product-duplicate-modal>Cancelar</button>
                                        <button type="submit" class="primary-button">Duplicar cat&aacute;logo</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="product-duplicate-empty">
                                <strong>Nenhum per&iacute;odo anterior encontrado</strong>
                                <p class="muted">Cadastre ou importe um cat&aacute;logo anterior antes de criar a base do m&ecirc;s atual.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('products/save')) ?>" class="form-grid audit-form">
            <?= \App\Core\Csrf::input() ?>
            <input type="hidden" name="id" value="<?= e((string) $productForm['id']) ?>">

            <label class="wide">
                <span>PRODUTO_NOME</span>
                <input type="text" name="product_name" value="<?= e($productForm['product_name']) ?>" required data-uppercase>
            </label>

            <label>
                <span>PRODUTO_VALOR_GERENCIAL</span>
                <input type="text" name="managerial_value" value="<?= e((string) $productForm['managerial_value']) ?>" inputmode="decimal">
            </label>

            <label>
                <span>PRODUTO_PONTUA&Ccedil;&Atilde;O_COMERCIAL</span>
                <input type="text" name="commercial_score" value="<?= e((string) $productForm['commercial_score']) ?>" inputmode="decimal">
            </label>

            <label>
                <span>FATOR</span>
                <input type="text" name="factor" value="<?= e((string) $productForm['factor']) ?>" inputmode="decimal">
            </label>

            <label>
                <span>CATEGORIA</span>
                <input type="text" name="category" value="<?= e((string) $productForm['category']) ?>" data-uppercase>
            </label>

            <label>
                <span>TIPO</span>
                <input type="text" name="type" value="<?= e((string) $productForm['type']) ?>" data-uppercase>
            </label>

            <label>
                <span>VIVO_TOTAL</span>
                <input type="text" name="vivo_total" value="<?= e((string) $productForm['vivo_total']) ?>" data-uppercase>
            </label>

            <label>
                <span>TIPO_DOC</span>
                <select name="document_type" required>
                    <?php foreach (['B2B', 'B2C'] as $documentType): ?>
                        <option value="<?= e($documentType) ?>" <?= ($productForm['document_type'] ?? 'B2C') === $documentType ? 'selected' : '' ?>>
                            <?= e($documentType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>SOLO</span>
                <input type="text" name="solo" value="<?= e((string) $productForm['solo']) ?>" data-uppercase>
            </label>

            <label>
                <span>2P</span>
                <input type="text" name="two_p" value="<?= e((string) $productForm['two_p']) ?>" data-uppercase>
            </label>

            <label>
                <span>3P</span>
                <input type="text" name="three_p" value="<?= e((string) $productForm['three_p']) ?>" data-uppercase>
            </label>

            <label>
                <span>DUO</span>
                <input type="text" name="duo" value="<?= e((string) $productForm['duo']) ?>" data-uppercase>
            </label>

            <label>
                <span>PER&Iacute;ODO</span>
                <input type="text" name="period" value="<?= e((string) $productForm['period']) ?>" required maxlength="6" inputmode="numeric" pattern="\d{6}" data-only-digits>
            </label>

            <label>
                <span>ATIVO</span>
                <select name="active" required>
                    <option value="1" <?= (int) ($productForm['active'] ?? 1) === 1 ? 'selected' : '' ?>>SIM</option>
                    <option value="0" <?= (int) ($productForm['active'] ?? 1) === 0 ? 'selected' : '' ?>>N&Atilde;O</option>
                </select>
            </label>

            <?php if ($productForm['id']): ?>
                <section
                    class="wide inner-card product-recalc-panel<?= ! empty($productRecalculationForm['is_open']) ? ' is-open' : '' ?>"
                    data-product-recalc-panel
                    <?= ! empty($productRecalculationForm['is_open']) ? '' : 'hidden' ?>
                >
                    <div class="section-header compact-section-header">
                        <div>
                            <p class="eyebrow">Rec&aacute;lculo</p>
                            <h4>Recalcular vendas finalizadas</h4>
                        </div>
                        <button type="button" class="ghost-link" data-product-recalc-cancel>Fechar</button>
                    </div>

                    <div class="product-recalc-grid">
                        <div
                            class="date-range-field"
                            data-date-range
                            data-initial-start="<?= e($productRecalculationForm['date_from'] ?? '') ?>"
                            data-initial-end="<?= e($productRecalculationForm['date_to'] ?? '') ?>"
                        >
                            <span>Faixa de data input</span>
                            <input type="hidden" name="recalculate_from" value="<?= e($productRecalculationForm['date_from'] ?? '') ?>" data-date-range-start>
                            <input type="hidden" name="recalculate_to" value="<?= e($productRecalculationForm['date_to'] ?? '') ?>" data-date-range-end>

                            <button type="button" class="date-range-trigger" data-date-range-trigger aria-expanded="false">
                                <span data-date-range-label><?= e($productRecalculationForm['date_range_label'] ?? 'Selecionar data ou intervalo') ?></span>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M7 2v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8H5v8h14v-8z"></path>
                                </svg>
                            </button>

                            <div class="date-range-picker" data-date-range-picker hidden>
                                <div class="date-range-head">
                                    <button type="button" class="date-range-nav" data-date-range-prev aria-label="M&ecirc;s anterior">
                                        <span aria-hidden="true">&#8249;</span>
                                    </button>
                                    <strong data-date-range-month></strong>
                                    <button type="button" class="date-range-nav" data-date-range-next aria-label="Pr&oacute;ximo m&ecirc;s">
                                        <span aria-hidden="true">&#8250;</span>
                                    </button>
                                </div>

                                <div class="date-range-weekdays">
                                    <span>D</span>
                                    <span>S</span>
                                    <span>T</span>
                                    <span>Q</span>
                                    <span>Q</span>
                                    <span>S</span>
                                    <span>S</span>
                                </div>

                                <div class="date-range-grid" data-date-range-grid></div>

                                <div class="date-range-footer">
                                    <small class="muted" data-date-range-summary><?= e($productRecalculationForm['date_range_label'] ?? 'Selecionar data ou intervalo') ?></small>
                                    <div class="form-actions-right">
                                        <button type="button" class="ghost-button small-button" data-date-range-clear>Limpar data</button>
                                        <button type="button" class="secondary-button small-button" data-date-range-close>Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="muted product-recalc-hint">Selecione apenas a data inicial para recalcular a partir daquele dia. Se preencher in&iacute;cio e fim, o sistema altera somente o intervalo escolhido.</p>

                        <div class="form-actions-right">
                            <button type="submit" class="success-button" name="save_mode" value="recalculate">Confirmar rec&aacute;lculo</button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <div class="wide form-actions">
                <?php if ($productForm['id']): ?>
                    <a href="<?= e(url('products')) ?>" class="ghost-link">Cancelar edi&ccedil;&atilde;o</a>
                <?php else: ?>
                    <span class="muted">Os campos podem ser ajustados manualmente conforme o cat&aacute;logo comercial.</span>
                <?php endif; ?>
                <div class="form-actions-right">
                    <?php if ($productForm['id']): ?>
                        <button type="submit" class="primary-button" name="save_mode" value="save_only">Salvar</button>
                        <button type="button" class="success-button" data-product-recalc-open>Salvar e recalcular</button>
                    <?php else: ?>
                        <button type="submit" class="primary-button">Cadastrar produto</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <p class="eyebrow">Consulta</p>
        <h3>Produtos cadastrados</h3>
    </div>

    <form method="get" class="filters products-filters-form">
        <input type="hidden" name="route" value="products">
        <label class="products-filter-search">
            <span>Busca</span>
            <input type="text" name="term" value="<?= e($productTermFilter) ?>" placeholder="Produto, tipo, categoria, per&iacute;odo..." data-uppercase>
        </label>

        <label class="products-filter-select">
            <span>Tipo</span>
            <select name="document_type">
                <option value="">Todos</option>
                <option value="B2C" <?= ($productDocumentTypeFilter ?? '') === 'B2C' ? 'selected' : '' ?>>B2C</option>
                <option value="B2B" <?= ($productDocumentTypeFilter ?? '') === 'B2B' ? 'selected' : '' ?>>B2B</option>
            </select>
        </label>

        <label class="products-filter-select">
            <span>Vivo Total</span>
            <select name="vivo_total">
                <option value="">Todos</option>
                <option value="TOTAL" <?= strtoupper((string) ($productVivoTotalFilter ?? '')) === 'TOTAL' ? 'selected' : '' ?>>Somente Total</option>
            </select>
        </label>

        <label class="products-filter-select">
            <span>Categoria</span>
            <select name="category">
                <option value="">Todas</option>
                <?php foreach ($productCategories as $category): ?>
                    <option value="<?= e($category) ?>" <?= ($productCategoryFilter ?? '') === $category ? 'selected' : '' ?>>
                        <?= e($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="secondary-button">Filtrar</button>
        <a href="<?= e(url('products')) ?>" class="icon-button" data-ui-tooltip="Limpar filtros" aria-label="Limpar filtros">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16.24 3.56a2 2 0 0 1 2.83 0l1.37 1.37a2 2 0 0 1 0 2.83l-8.48 8.48a2 2 0 0 1-1.41.59H7.41a2 2 0 0 1-1.41-.59l-2.44-2.44a2 2 0 0 1 0-2.83l8.48-8.48a2 2 0 0 1 2.83 0l1.37 1.37zm-9.83 8.97 2.82 2.82h1.32l7.76-7.76-2.82-2.82-9.08 9.08zm-1.42 5.24h14v2H5v-2z"></path>
            </svg>
        </a>
    </form>

    <div class="table-wrap compact-table hierarchy-table-wrap products-table-wrap">
        <table class="hierarchy-table products-table">
            <thead>
            <tr>
                <th>PRODUTO</th>
                <th>TIPO</th>
                <th>CATEGORIA</th>
                <th>TIPO PRODUTO</th>
                <th>VALOR GERENCIAL</th>
                <th>PONTUA&Ccedil;&Atilde;O</th>
                <th>PER&Iacute;ODO</th>
                <th>STATUS</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="9" class="empty-state">Nenhum produto encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= e($product['PRODUTO_NOME']) ?></td>
                        <td><?= e($product['TIPO_DOC']) ?></td>
                        <td><?= e($product['CATEGORIA']) ?></td>
                        <td><?= e($product['TIPO']) ?></td>
                        <td><?= e(number_format((float) ($product['PRODUTO_VALOR_GERENCIAL'] ?? 0), 2, ',', '.')) ?></td>
                        <td><?= e(number_format((float) ($product['PRODUTO_PONTUACAO_COMERCIAL'] ?? 0), 2, ',', '.')) ?></td>
                        <td><?= e($product['PERIODO'] ?? '-') ?></td>
                        <td>
                            <span class="status-pill <?= (int) $product['ativo'] === 1 ? 'status-finalizada' : 'status-pendente-input' ?>">
                                <?= (int) $product['ativo'] === 1 ? 'ATIVO' : 'INATIVO' ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="<?= e(url('products', ['edit_product' => $product['id']])) ?>" class="ghost-link small-button product-edit-link">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
