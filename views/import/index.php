<section class="panel dashboard-filters-panel is-open" data-collapsible-panel>
    <button
        type="button"
        class="dashboard-filter-toggle"
        data-collapsible-toggle
        aria-expanded="true"
    >
        <span class="dashboard-filter-title">
            <small class="eyebrow">Carga recorrente</small>
            <strong>Importar arquivo ZIP</strong>
        </span>
        <span class="dashboard-filter-icon" data-collapsible-icon>&minus;</span>
    </button>

    <div class="dashboard-filter-body" data-collapsible-body>
        <form method="post" enctype="multipart/form-data" class="zip-import-form">
            <?= \App\Core\Csrf::input() ?>

            <label class="zip-upload-field" data-file-field>
                <span>Arquivo ZIP</span>
                <span class="upload-field-control" data-file-control>
                    <input type="file" name="zip_file" accept=".zip" required data-file-input data-empty-label="Nenhum arquivo escolhido">
                    <span class="upload-field-button" aria-hidden="true">Escolher arquivo</span>
                    <span class="upload-field-name" data-file-name>Nenhum arquivo escolhido</span>
                </span>
            </label>

            <button type="submit" class="secondary-button zip-import-submit">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Importar ZIP</span>
            </button>
        </form>

        <p class="muted zip-import-note">
            O sistema s&oacute; grava linhas com Status do Servi&ccedil;o = Aprovado, Status da Venda = Finalizada e Servi&ccedil;o = BANDA LARGA.
        </p>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <p class="eyebrow">Hist&oacute;rico</p>
        <h3>&Uacute;ltimos uploads</h3>
    </div>

    <div class="table-wrap">
        <table class="import-history-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Arquivo ZIP</th>
                <th>Total</th>
                <th>Eleg&iacute;veis</th>
                <th>Novas</th>
                <th>Duplicadas</th>
                <th>Filtradas</th>
                <th>Criado em</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentBatches === []): ?>
                <tr>
                    <td colspan="8" class="empty-state">Ainda n&atilde;o existe hist&oacute;rico de importa&ccedil;&atilde;o.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentBatches as $batch): ?>
                    <tr>
                        <td><?= e((string) $batch['id']) ?></td>
                        <td><?= e($batch['original_filename']) ?></td>
                        <td><?= e((string) $batch['total_rows']) ?></td>
                        <td><?= e((string) $batch['eligible_rows']) ?></td>
                        <td><?= e((string) $batch['imported_count']) ?></td>
                        <td><?= e((string) $batch['duplicate_count']) ?></td>
                        <td><?= e((string) $batch['filtered_out_count']) ?></td>
                        <td><?= e(format_datetime_br($batch['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
