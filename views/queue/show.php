<?php
$selectedProductIds = array_values(array_filter(array_map('intval', $formData['product_ids'] ?? []), static fn (int $productId): bool => $productId > 0));
$saleLogs = $saleLogs ?? [];
$isFinalizedView = ($queueItem['audit_status'] ?? '') === 'FINALIZADA';
$queueContextParams = $queueContextParams ?? [];

$catalogManagerialValues = [];
$catalogProductsById = [];
$fixedDataCatalogProducts = [];
$fixedVoiceCatalogProducts = [];
$fixedTvCatalogProducts = [];
$mobileCatalogProducts = [];
$additionalCatalogProducts = [];

foreach ($catalogProducts as $catalogProduct) {
    $catalogProductId = (int) ($catalogProduct['id'] ?? 0);
    $catalogCategory = strtoupper(normalize_text((string) ($catalogProduct['category'] ?? $catalogProduct['CATEGORIA'] ?? '')));
    $catalogType = strtoupper(normalize_text((string) ($catalogProduct['type'] ?? $catalogProduct['TIPO'] ?? '')));

    $catalogProductsById[$catalogProductId] = $catalogProduct;
    $catalogManagerialValues[$catalogProductId] = (float) ($catalogProduct['managerial_value'] ?? 0);

    if (in_array($catalogType, ['ADICIONAL', 'SVA'], true)) {
        $additionalCatalogProducts[] = $catalogProduct;
        continue;
    }

    if ($catalogCategory === 'MOVEL') {
        $mobileCatalogProducts[] = $catalogProduct;
        continue;
    }

    if ($catalogType === 'VOZ') {
        $fixedVoiceCatalogProducts[] = $catalogProduct;
        continue;
    }

    if ($catalogType === 'TV') {
        $fixedTvCatalogProducts[] = $catalogProduct;
        continue;
    }

    $fixedDataCatalogProducts[] = $catalogProduct;
}

$selectedProductsManagerialTotal = array_reduce(
    $selectedProductIds,
    static fn (float $carry, int $productId): float => $carry + (float) ($catalogManagerialValues[$productId] ?? 0),
    0.0
);

$selectedFixedDataProductId = 0;
$selectedFixedVoiceProductId = 0;
$selectedFixedTvProductId = 0;
$selectedMobileProductId = 0;
$selectedAdditionalProductIds = [];

foreach ($selectedProductIds as $selectedProductId) {
    $selectedProduct = $catalogProductsById[$selectedProductId] ?? null;

    if ($selectedProduct === null) {
        continue;
    }

    $selectedCategory = strtoupper(normalize_text((string) ($selectedProduct['category'] ?? $selectedProduct['CATEGORIA'] ?? '')));
    $selectedType = strtoupper(normalize_text((string) ($selectedProduct['type'] ?? $selectedProduct['TIPO'] ?? '')));

    if (in_array($selectedType, ['ADICIONAL', 'SVA'], true)) {
        $selectedAdditionalProductIds[] = $selectedProductId;
        continue;
    }

    if ($selectedCategory === 'MOVEL') {
        if ($selectedMobileProductId === 0) {
            $selectedMobileProductId = $selectedProductId;
        }
        continue;
    }

    if ($selectedType === 'VOZ') {
        if ($selectedFixedVoiceProductId === 0) {
            $selectedFixedVoiceProductId = $selectedProductId;
        }
        continue;
    }

    if ($selectedType === 'TV') {
        if ($selectedFixedTvProductId === 0) {
            $selectedFixedTvProductId = $selectedProductId;
        }
        continue;
    }

    if ($selectedFixedDataProductId === 0) {
        $selectedFixedDataProductId = $selectedProductId;
    }
}

if ($selectedAdditionalProductIds === []) {
    $selectedAdditionalProductIds = [0];
}

$summaryCards = [
    ['label' => 'Código da venda', 'value' => (string) ($queueItem['sale_code'] ?? ''), 'editable' => false],
    ['label' => 'PDV Adabas', 'field' => 'pdv_adabas', 'value' => (string) ($formData['pdv_adabas'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'Data input', 'value' => trim((string) format_date_br($queueItem['sale_input_date'] ?? null) . ' ' . (($queueItem['sale_input_time'] ?? '') !== '' ? substr((string) $queueItem['sale_input_time'], 0, 5) : '')), 'editable' => false],
    ['label' => 'Modalidade', 'value' => (string) ($formData['service_type'] ?? ''), 'editable' => false],
    ['label' => 'CNL AT', 'field' => 'cabinet_code', 'value' => (string) ($formData['cabinet_code'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'Tipo cliente', 'value' => (string) (($formData['customer_document_type'] ?? '') !== '' ? $formData['customer_document_type'] : ($queueItem['customer_document_type'] ?? '')), 'editable' => false],
    ['label' => 'Data de nascimento', 'field' => 'customer_birth_date', 'value' => (string) ($formData['customer_birth_date'] ?? ''), 'display' => format_date_br($formData['customer_birth_date'] ?? null), 'editable' => true, 'input_type' => 'date', 'attention' => true],
    ['label' => 'Mãe / Nome fantasia', 'field' => 'customer_mother_name', 'value' => (string) ($formData['customer_mother_name'] ?? ''), 'editable' => true, 'uppercase' => true, 'attention' => true],
    ['label' => 'Documento', 'field' => 'customer_document', 'value' => (string) ($formData['customer_document'] ?? ''), 'editable' => true, 'digits' => true, 'input_mode' => 'numeric', 'maxlength' => 20],
    ['label' => 'Cliente', 'field' => 'customer_name', 'value' => (string) ($formData['customer_name'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'Celular', 'field' => 'phone_mobile', 'value' => (string) ($formData['phone_mobile'] ?? ''), 'editable' => true, 'input_mode' => 'tel', 'maxlength' => 11, 'digits' => true],
    ['label' => 'Telefone 1', 'field' => 'phone_alt_1', 'value' => (string) ($formData['phone_alt_1'] ?? ''), 'editable' => true, 'input_mode' => 'tel', 'maxlength' => 11, 'digits' => true],
    ['label' => 'Telefone 2', 'field' => 'phone_alt_2', 'value' => (string) ($formData['phone_alt_2'] ?? ''), 'editable' => true, 'input_mode' => 'tel', 'maxlength' => 11, 'digits' => true],
    ['label' => 'E-mail', 'field' => 'customer_email', 'value' => (string) ($formData['customer_email'] ?? ''), 'editable' => true, 'input_type' => 'email'],
    ['label' => 'Cidade / UF', 'value' => trim((string) ($formData['customer_city'] ?? '') . ' / ' . (string) ($formData['customer_uf'] ?? ''), ' /'), 'editable' => false],
    ['label' => 'Regional cliente', 'field' => 'customer_regional', 'value' => (string) ($formData['customer_regional'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'CEP', 'value' => (string) ($formData['customer_cep'] ?? ''), 'editable' => false],
    ['label' => 'Serviço', 'field' => 'service_name', 'value' => (string) ($formData['service_name'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'CPF vendedor', 'value' => (string) ($queueItem['seller_cpf'] ?? ''), 'editable' => false],
    ['label' => 'Regional', 'field' => 'sale_regional', 'value' => (string) ($formData['sale_regional'] ?? ''), 'editable' => true, 'uppercase' => true],
    ['label' => 'DDD', 'field' => 'ddd', 'value' => (string) ($formData['ddd'] ?? ''), 'editable' => true, 'maxlength' => 5],
    ['label' => 'Tipo de venda', 'field' => 'sale_type', 'value' => (string) ($formData['sale_type'] ?? ''), 'editable' => true, 'uppercase' => true],
];

$summaryEditableFields = [];

$productReferenceItems = [
    ['label' => 'Plano', 'value' => (string) (($formData['plan_name'] ?? '') !== '' ? $formData['plan_name'] : ($queueItem['plan_name'] ?? ''))],
    ['label' => 'Pacote', 'value' => (string) (($formData['package_name'] ?? '') !== '' ? $formData['package_name'] : ($queueItem['package_name'] ?? ''))],
    ['label' => 'Composi&ccedil;&atilde;o', 'value' => (string) (($formData['composition_name'] ?? '') !== '' ? $formData['composition_name'] : ($queueItem['composition_name'] ?? ''))],
    ['label' => 'Servi&ccedil;os adicionais', 'value' => (string) ($queueItem['additional_services'] ?? '')],
    ['label' => 'Pontos adicionais', 'value' => (string) ($queueItem['additional_points'] ?? '')],
];
foreach ($summaryCards as $summaryCard) {
    if (($summaryCard['editable'] ?? false) && ! empty($summaryCard['field'])) {
        $summaryEditableFields[$summaryCard['field']] = $summaryCard['value'] ?? '';
    }
}
?>

<section class="detail-shell">
    <article class="panel">
        <div class="panel-head">
            <p class="eyebrow">Etapa 1</p>
            <h3>Resumo da venda</h3>
            <p>
                <?= $isFinalizedView ? 'Modo de visualiza&ccedil;&atilde;o e edi&ccedil;&atilde;o da venda finalizada.' : 'Usu&aacute;rio atual: ' . e($authUser['name'] ?? '-') . ' | Pegou em ' . e(format_datetime_br($queueItem['claimed_at'])) ?>
            </p>
        </div>

        <div class="detail-grid summary-card-grid">
            <?php foreach ($summaryCards as $card): ?>
                <?php
                $cardValue = (string) ($card['value'] ?? '');
                $cardDisplay = normalize_text((string) ($card['display'] ?? $cardValue));
                $cardDisplay = $cardDisplay !== '' ? $cardDisplay : 'Não informado';
                $cardCopy = $cardDisplay !== 'Não informado' ? $cardDisplay : '';
                $isEditableCard = (bool) ($card['editable'] ?? false) && ! empty($card['field']);
                $needsAttention = ! empty($card['attention']) && trim($cardValue) === '';
                ?>
                <button
                    type="button"
                    class="detail-card<?= $isEditableCard ? ' is-editable' : ' is-copyable' ?><?= $needsAttention ? ' is-pulsing' : '' ?>"
                    data-summary-card
                    data-summary-label="<?= e($card['label']) ?>"
                    data-summary-display="<?= e($cardDisplay) ?>"
                    data-summary-value="<?= e($cardValue) ?>"
                    data-summary-copy="<?= e($cardCopy) ?>"
                    data-summary-editable="<?= $isEditableCard ? '1' : '0' ?>"
                    data-summary-attention="<?= ! empty($card['attention']) ? '1' : '0' ?>"
                    <?php if ($isEditableCard): ?>
                        data-summary-field="<?= e((string) $card['field']) ?>"
                        data-summary-input-type="<?= e((string) ($card['input_type'] ?? 'text')) ?>"
                        data-summary-input-mode="<?= e((string) ($card['input_mode'] ?? 'text')) ?>"
                        data-summary-maxlength="<?= e((string) ($card['maxlength'] ?? '255')) ?>"
                        data-summary-uppercase="<?= ! empty($card['uppercase']) ? '1' : '0' ?>"
                        data-summary-digits="<?= ! empty($card['digits']) ? '1' : '0' ?>"
                    <?php endif; ?>
                >
                    <span><?= e($card['label']) ?></span>
                    <strong data-summary-card-value><?= e($cardDisplay) ?></strong>
                    <small><?= $isEditableCard ? 'Clique para editar ou copiar' : 'Clique para copiar' ?></small>
                </button>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <p class="eyebrow"><?= $isFinalizedView ? 'Venda cadastrada' : 'Fluxo guiado' ?></p>
            <h3><?= $isFinalizedView ? 'Visualizar e editar venda' : 'Preencha as etapas para concluir a auditoria' ?></h3>
        </div>

        <form method="post" action="<?= e(url('queue/save', ['id' => $queueItem['id']] + $queueContextParams)) ?>" class="audit-flow-form" data-audit-steps-root>
            <?= \App\Core\Csrf::input() ?>
            <?php foreach ($summaryEditableFields as $field => $value): ?>
                <input type="hidden" name="<?= e($field) ?>" value="<?= e((string) $value) ?>" data-summary-form-input="<?= e($field) ?>">
            <?php endforeach; ?>
            <section class="audit-step" data-audit-step data-step-kind="order">
                <button type="button" class="audit-step-toggle" data-step-toggle aria-expanded="false">
                    <span class="audit-step-label">Etapa 2</span>
                    <span class="audit-step-title-block">
                        <strong>Ordem de servi&ccedil;o e agendamento</strong>
                        <small>Preencha a ordem de servi&ccedil;o e a data do agendamento.</small>
                    </span>
                    <span class="audit-step-state" data-step-state>Bloqueada</span>
                    <span class="audit-step-icon" data-step-icon>+</span>
                </button>

                <div class="audit-step-body" data-step-body hidden>
                    <div class="audit-step-grid audit-step-grid-2">
                        <label><span>Ordem de servi&ccedil;o</span><input type="text" name="service_order" value="<?= e($formData['service_order'] ?? '') ?>" data-step-required required></label>
                        <label><span>Data Agendamento Instala&ccedil;&atilde;o</span><input type="date" name="audit_installation_date" value="<?= e($formData['audit_installation_date'] ?? '') ?>" data-step-required required></label>
                        <label><span>Inst&acirc;ncia</span><input type="text" name="sale_instance" value="<?= e($formData['sale_instance'] ?? '') ?>" data-uppercase></label>
                        <label><span>Protocolo</span><input type="text" name="sale_protocol" value="<?= e($formData['sale_protocol'] ?? '') ?>" data-uppercase></label>
                    </div>
                </div>
            </section>

            <section class="audit-step" data-audit-step data-step-kind="address">
                <button type="button" class="audit-step-toggle" data-step-toggle aria-expanded="false">
                    <span class="audit-step-label">Etapa 3</span>
                    <span class="audit-step-title-block">
                        <strong>Endere&ccedil;o</strong>
                        <small>Confirme e complete o endere&ccedil;o da venda.</small>
                    </span>
                    <span class="audit-step-state" data-step-state>Bloqueada</span>
                    <span class="audit-step-icon" data-step-icon>+</span>
                </button>

                <div class="audit-step-body" data-step-body hidden>
                    <div class="address-grid">
                        <label>
                            <span>CEP</span>
                            <input
                                type="text"
                                name="customer_cep"
                                value="<?= e($formData['customer_cep'] ?? '') ?>"
                                maxlength="8"
                                inputmode="numeric"
                                data-only-digits
                                data-cep-input
                                data-step-required
                                required
                            >
                            <small class="muted address-lookup-status" data-cep-status>Digite o CEP com 8 n&uacute;meros para preencher o endere&ccedil;o.</small>
                        </label>

                        <label class="address-span-2">
                            <span>Logradouro</span>
                            <input type="text" name="customer_street_name" value="<?= e($formData['customer_street_name'] ?? '') ?>" data-cep-field="logradouro" data-uppercase data-step-required required>
                        </label>

                        <label>
                            <span>N&uacute;mero</span>
                            <input type="text" name="customer_street_number" value="<?= e($formData['customer_street_number'] ?? '') ?>" data-uppercase data-step-required required>
                        </label>

                        <label>
                            <span>Complemento</span>
                            <input type="text" name="customer_street_complement" value="<?= e($formData['customer_street_complement'] ?? '') ?>" data-uppercase>
                        </label>

                        <label>
                            <span>Bairro</span>
                            <input type="text" name="customer_neighborhood" value="<?= e($formData['customer_neighborhood'] ?? '') ?>" data-cep-field="bairro" data-uppercase data-step-required required>
                        </label>

                        <label>
                            <span>UF</span>
                            <input type="text" name="customer_uf" value="<?= e($formData['customer_uf'] ?? '') ?>" maxlength="2" data-cep-field="uf" data-uppercase data-step-required required>
                        </label>

                        <label class="address-span-2">
                            <span>Cidade</span>
                            <input type="text" name="customer_city" value="<?= e($formData['customer_city'] ?? '') ?>" data-cep-field="cidade" data-uppercase data-step-required required>
                        </label>
                    </div>
                </div>
            </section>

            <section class="audit-step" data-audit-step data-step-kind="hierarchy">
                <button type="button" class="audit-step-toggle" data-step-toggle aria-expanded="false">
                    <span class="audit-step-label">Etapa 4</span>
                    <span class="audit-step-title-block">
                        <strong>Hierarquia</strong>
                        <small>Defina o consultor respons&aacute;vel e a hierarquia comercial.</small>
                    </span>
                    <span class="audit-step-state" data-step-state>Bloqueada</span>
                    <span class="audit-step-icon" data-step-icon>+</span>
                </button>

                <div class="audit-step-body" data-step-body hidden>
                    <?php if (! empty($sellerHierarchy)): ?>
                        <div class="flash flash-success">
                            Hierarquia localizada pelo CPF do vendedor. Os campos de consultor, supervisor, coordenador, gerente, base nome e base grupo j&aacute; vieram preenchidos.
                        </div>
                    <?php else: ?>
                        <div class="flash flash-error">
                            N&atilde;o encontrei hierarquia para o CPF <?= e($queueItem['seller_cpf']) ?>. Se precisar, cadastre primeiro no m&oacute;dulo de hierarquia.
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="consultant_cpf" value="<?= e($formData['consultant_cpf'] ?? '') ?>" data-consultant-field="seller_cpf" data-step-required>
                    <input type="hidden" name="consultant_base_regional" value="<?= e($formData['consultant_base_regional'] ?? '') ?>" data-consultant-field="consultant_base_regional">
                    <input type="hidden" name="consultant_sector_name" value="<?= e($formData['consultant_sector_name'] ?? '') ?>" data-consultant-field="consultant_sector_name">
                    <input type="hidden" name="consultant_sector_type" value="<?= e($formData['consultant_sector_type'] ?? '') ?>" data-consultant-field="consultant_sector_type">
                    <input type="hidden" name="territory_manager_name" value="<?= e($formData['territory_manager_name'] ?? '') ?>" data-consultant-field="territory_manager_name">

                    <div class="section-header compact-section-header">
                        <div>
                            <span class="eyebrow">Consultor</span>
                            <h4>Respons&aacute;vel e hierarquia</h4>
                        </div>
                        <button type="button" class="secondary-button small-button" data-open-consultant-modal>Trocar Consultor</button>
                    </div>

                    <div class="audit-step-grid audit-step-grid-3">
                        <label><span>Consultor nome</span><input type="text" name="consultant_name" value="<?= e($formData['consultant_name'] ?? '') ?>" data-consultant-field="seller_name" data-step-required required></label>
                        <label><span>Consultor tipo</span><input type="text" name="consultant_type" value="<?= e($formData['consultant_type'] ?? '') ?>" data-consultant-field="role" data-step-required required></label>
                        <label><span>Consultor base nome</span><input type="text" name="consultant_base_name" value="<?= e($formData['consultant_base_name'] ?? '') ?>" data-consultant-field="base_name" data-step-required required></label>
                        <label><span>Consultor base grupo</span><input type="text" name="consultant_base_group" value="<?= e($formData['consultant_base_group'] ?? '') ?>" data-consultant-field="base_group_name" data-step-required required></label>
                        <label><span>Supervisor nome</span><input type="text" name="supervisor_name" value="<?= e($formData['supervisor_name'] ?? '') ?>" data-consultant-field="supervisor_name" data-step-required required></label>
                        <label><span>Coordenador nome</span><input type="text" name="coordinator_name" value="<?= e($formData['coordinator_name'] ?? '') ?>" data-consultant-field="coordinator_name" data-step-required required></label>
                        <label><span>Gerente base nome</span><input type="text" name="base_manager_name" value="<?= e($formData['base_manager_name'] ?? '') ?>" data-consultant-field="manager_name" data-step-required required></label>
                    </div>
                </div>
            </section>

            <section class="audit-step" data-audit-step data-step-kind="products">
                <button type="button" class="audit-step-toggle" data-step-toggle aria-expanded="false">
                    <span class="audit-step-label">Etapa 5</span>
                    <span class="audit-step-title-block">
                        <strong>Produtos</strong>
                        <small>Selecione um ou mais produtos para gravar a venda.</small>
                    </span>
                    <span class="audit-step-state" data-step-state>Bloqueada</span>
                    <span class="audit-step-icon" data-step-icon>+</span>
                </button>

                <div class="audit-step-body" data-step-body hidden>
                    <div class="section-header compact-section-header">
                        <div>
                            <span class="eyebrow">Produtos</span>
                            <h4>Selecione os produtos por categoria</h4>
                        </div>
                    </div>

                    <div class="product-reference-grid">
                        <?php foreach ($productReferenceItems as $item): ?>
                            <?php $referenceValue = trim((string) ($item['value'] ?? '')); ?>
                            <article class="product-reference-card">
                                <span><?= $item['label'] ?></span>
                                <strong><?= $referenceValue !== '' ? e($referenceValue) : 'N&atilde;o informado' ?></strong>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="product-choice-grid">
                        <article class="product-choice-card">
                            <div class="product-choice-head">
                                <span class="eyebrow">Fixa</span>
                                <small>Selecione at&eacute; 1 produto</small>
                            </div>

                            <select name="product_ids[]" data-product-select data-product-group="fixed-data">
                                <option value="">Selecione um produto FIXA - DADOS</option>
                                <?php foreach ($fixedDataCatalogProducts as $product): ?>
                                    <option
                                        value="<?= e((string) $product['id']) ?>"
                                        data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>"
                                        data-product-name="<?= e((string) ($product['name'] ?? '')) ?>"
                                        data-vivo-total="<?= e((string) ($product['vivo_total'] ?? $product['VIVO_TOTAL'] ?? '')) ?>"
                                        <?= $selectedFixedDataProductId === (int) $product['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($product['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </article>

                        <article class="product-choice-card">
                            <div class="product-choice-head">
                                <span class="eyebrow">Fixa voz</span>
                                <small>Selecione at&eacute; 1 produto</small>
                            </div>

                            <select name="product_ids[]" data-product-select data-product-group="fixed-voice">
                                <option value="">Selecione um produto FIXA - VOZ</option>
                                <?php foreach ($fixedVoiceCatalogProducts as $product): ?>
                                    <option
                                        value="<?= e((string) $product['id']) ?>"
                                        data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>"
                                        data-product-name="<?= e((string) ($product['name'] ?? '')) ?>"
                                        data-vivo-total="<?= e((string) ($product['vivo_total'] ?? $product['VIVO_TOTAL'] ?? '')) ?>"
                                        <?= $selectedFixedVoiceProductId === (int) $product['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($product['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </article>

                        <article class="product-choice-card">
                            <div class="product-choice-head">
                                <span class="eyebrow">Fixa TV</span>
                                <small>Selecione at&eacute; 1 produto</small>
                            </div>

                            <select name="product_ids[]" data-product-select data-product-group="fixed-tv">
                                <option value="">Selecione um produto FIXA - TV</option>
                                <?php foreach ($fixedTvCatalogProducts as $product): ?>
                                    <option
                                        value="<?= e((string) $product['id']) ?>"
                                        data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>"
                                        data-product-name="<?= e((string) ($product['name'] ?? '')) ?>"
                                        data-vivo-total="<?= e((string) ($product['vivo_total'] ?? $product['VIVO_TOTAL'] ?? '')) ?>"
                                        <?= $selectedFixedTvProductId === (int) $product['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($product['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </article>

                        <article class="product-choice-card">
                            <div class="product-choice-head">
                                <span class="eyebrow">M&oacute;vel</span>
                                <small>Selecione at&eacute; 1 produto</small>
                            </div>

                            <select name="product_ids[]" data-product-select data-product-group="mobile">
                                <option value="">Selecione um produto M&Oacute;VEL</option>
                                <?php foreach ($mobileCatalogProducts as $product): ?>
                                    <option
                                        value="<?= e((string) $product['id']) ?>"
                                        data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>"
                                        data-product-name="<?= e((string) ($product['name'] ?? '')) ?>"
                                        data-vivo-total="<?= e((string) ($product['vivo_total'] ?? $product['VIVO_TOTAL'] ?? '')) ?>"
                                        <?= $selectedMobileProductId === (int) $product['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($product['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </article>

                        <article class="product-choice-card product-choice-card-wide">
                            <div class="product-choice-head">
                                <div>
                                    <span class="eyebrow">Servi&ccedil;os adicionais</span>
                                    <small>Selecione at&eacute; 10 produtos do tipo ADICIONAL ou SVA</small>
                                </div>
                                <button type="button" class="secondary-button small-button" data-add-product-row>Adicionar servi&ccedil;o</button>
                            </div>

                            <div class="product-rows" data-product-rows>
                                <?php foreach ($selectedAdditionalProductIds as $productId): ?>
                                    <div class="product-row">
                                        <select name="product_ids[]" data-product-select data-product-group="additional">
                                            <option value="">Selecione um servi&ccedil;o adicional</option>
                                            <?php foreach ($additionalCatalogProducts as $product): ?>
                                                <option
                                                    value="<?= e((string) $product['id']) ?>"
                                                    data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>"
                                                    <?= (int) $productId === (int) $product['id'] ? 'selected' : '' ?>
                                                >
                                                    <?= e($product['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="ghost-button" data-remove-product-row>Remover</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="product-extra-meta">
                                <small data-additional-products-count><?= count(array_values(array_filter($selectedAdditionalProductIds, static fn (int $productId): bool => $productId > 0))) ?>/10 selecionados</small>
                            </div>
                        </article>
                    </div>

                    <div class="product-total-summary" data-product-total-summary>
                        <span>Total Plano</span>
                        <strong data-product-total-value><?= e(format_currency_br($selectedProductsManagerialTotal)) ?></strong>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <div class="form-actions-left">
                    <a href="<?= e(url('queue', $queueContextParams)) ?>" class="ghost-link">Voltar para fila</a>
                    <button type="button" class="secondary-button" data-toggle-sale-log aria-expanded="false" aria-controls="sale-log-panel">Log da venda</button>
                </div>
                <div class="form-actions-right">
                    <?php if (! $isFinalizedView): ?>
                        <button type="submit" form="abandon-sale-form" class="danger-button">Abandonar venda</button>
                    <?php endif; ?>
                    <button type="submit" class="primary-button"><?= $isFinalizedView ? 'Salvar altera&ccedil;&otilde;es' : 'Salvar e finalizar venda' ?></button>
                </div>
            </div>

            <div class="sale-log-panel" id="sale-log-panel" data-sale-log-panel hidden>
                <div class="section-header compact-section-header">
                    <div>
                        <span class="eyebrow">Hist&oacute;rico</span>
                        <h4>Log da venda</h4>
                    </div>
                </div>

                <?php if ($saleLogs === []): ?>
                    <p class="muted">Nenhum log registrado ainda.</p>
                <?php else: ?>
                    <div class="sale-log-list">
                        <?php foreach ($saleLogs as $log): ?>
                            <?php
                            $actionLabel = match ($log['action_type']) {
                                'ACESSO' => 'Acesso',
                                'ALTERACAO' => 'Alteração',
                                'FINALIZACAO' => 'Finalização',
                                default => normalize_text($log['action_type'] ?? ''),
                            };
                            $actionClass = match ($log['action_type']) {
                                'ACESSO' => 'is-access',
                                'ALTERACAO' => 'is-change',
                                'FINALIZACAO' => 'is-finalization',
                                default => '',
                            };
                            ?>
                            <article class="sale-log-item">
                                <div class="sale-log-item-head">
                                    <div class="sale-log-item-meta">
                                        <span class="sale-log-badge <?= e($actionClass) ?>"><?= e($actionLabel) ?></span>
                                        <strong><?= e($log['user_name'] ?? 'Sistema') ?></strong>
                                        <small><?= e(format_datetime_br($log['created_at'] ?? null)) ?></small>
                                    </div>
                                </div>
                                <p><?= e($log['summary'] ?? '') ?></p>

                                <?php if (! empty($log['changes']) && is_array($log['changes'])): ?>
                                    <div class="sale-log-changes">
                                        <?php foreach ($log['changes'] as $change): ?>
                                            <div class="sale-log-change-row">
                                                <strong><?= e($change['field'] ?? '-') ?></strong>
                                                <small>De: <?= e($change['from'] ?? 'Não informado') ?></small>
                                                <small>Para: <?= e($change['to'] ?? 'Não informado') ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if (! $isFinalizedView): ?>
            <form method="post" action="<?= e(url('queue/abandon', ['id' => $queueItem['id']] + $queueContextParams)) ?>" id="abandon-sale-form">
                <?= \App\Core\Csrf::input() ?>
            </form>
        <?php endif; ?>
    </article>
</section>

<div class="modal-shell" data-summary-modal hidden>
    <div class="modal-backdrop" data-close-summary-modal></div>
    <div class="modal-card summary-modal-card" role="dialog" aria-modal="true" aria-labelledby="summary-modal-title">
        <div class="section-header">
            <div>
                <p class="eyebrow">Resumo da venda</p>
                <h4 id="summary-modal-title" data-summary-modal-title>Campo da venda</h4>
            </div>
            <button type="button" class="ghost-button small-button" data-close-summary-modal>Fechar</button>
        </div>

        <p class="muted" data-summary-modal-help>Copie o valor ou ajuste o conteúdo deste card.</p>

        <div class="summary-modal-input-host" data-summary-modal-input-host></div>

        <div class="form-actions summary-modal-actions">
            <div class="form-actions-left">
                <button type="button" class="secondary-button" data-copy-summary-modal>Copiar valor</button>
            </div>
            <div class="form-actions-right">
                <button type="button" class="primary-button" data-apply-summary-modal>Aplicar alteração</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-shell" data-consultant-modal hidden>
    <div class="modal-backdrop" data-close-consultant-modal></div>
    <div class="modal-card consultant-modal-card" role="dialog" aria-modal="true" aria-labelledby="consultant-modal-title">
        <div class="section-header">
            <div>
                <p class="eyebrow">Trocar Consultor</p>
                <h4 id="consultant-modal-title">Pesquisar na hierarquia</h4>
            </div>
            <button type="button" class="ghost-button small-button" data-close-consultant-modal>Fechar</button>
        </div>

        <label>
            <span>Nome ou CPF do vendedor</span>
            <input
                type="text"
                placeholder="Digite ao menos 2 caracteres"
                data-consultant-search-input
                data-search-url="<?= e(url('queue/consultant-search')) ?>"
                data-search-period="<?= e(($queueItem['sale_input_date'] ?? '') !== '' ? date('Ym', strtotime((string) $queueItem['sale_input_date'])) : '') ?>"
            >
        </label>

        <p class="muted consultant-search-status" data-consultant-search-status>Digite para pesquisar por nome ou CPF.</p>
        <div class="consultant-search-results" data-consultant-search-results></div>
    </div>
</div>

<template id="product-row-template">
    <div class="product-row">
        <select name="product_ids[]" data-product-select data-product-group="additional">
            <option value="">Selecione um servi&ccedil;o adicional</option>
            <?php foreach ($additionalCatalogProducts as $product): ?>
                <option value="<?= e((string) $product['id']) ?>" data-managerial-value="<?= e((string) ($product['managerial_value'] ?? 0)) ?>">
                    <?= e($product['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="ghost-button" data-remove-product-row>Remover</button>
    </div>
</template>
