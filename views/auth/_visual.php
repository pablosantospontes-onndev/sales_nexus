<?php $authLogo = APP_ROOT . '/assets/nexuspgi_darkmode.png'; ?>

<aside class="auth-visual">
    <div class="auth-visual-inner">
        <div class="auth-visual-brand">
            <?php if (is_file($authLogo)): ?>
                <img src="assets/nexuspgi_darkmode.png" alt="Sales Nexus" class="auth-visual-logo">
            <?php else: ?>
                <h1 class="auth-visual-logo-fallback">SALES NEXUS</h1>
            <?php endif; ?>

            <p class="eyebrow">Sales Nexus CRM</p>
            <h1>Programa de Gest&atilde;o Interativa</h1>
        </div>

        <div class="auth-visual-graphic" aria-hidden="true">
            <div class="auth-system-preview">
                <div class="auth-system-window">
                    <div class="auth-system-topbar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="auth-system-body">
                        <aside class="auth-system-sidebar">
                            <?php if (is_file($authLogo)): ?>
                                <img src="assets/nexuspgi_darkmode.png" alt="" class="auth-system-sidebar-logo">
                            <?php else: ?>
                                <span class="auth-system-sidebar-fallback">NEXUS</span>
                            <?php endif; ?>

                            <span class="auth-system-nav-item">Dashboard</span>
                            <span class="auth-system-nav-item">Importar ZIP</span>
                            <span class="auth-system-nav-item is-active">Fila de auditoria</span>
                            <span class="auth-system-nav-item">Produtos</span>

                            <div class="auth-system-sidebar-user">
                                <strong>Backoffice</strong>
                                <small>Em atendimento</small>
                            </div>
                        </aside>

                        <div class="auth-system-content">
                            <div class="auth-system-header">
                                <small>SALES NEXUS</small>
                                <strong>Fila de auditoria</strong>
                            </div>

                            <div class="auth-system-filter-row">
                                <span>Todos</span>
                                <span>B2C</span>
                                <span>31/03/2026</span>
                            </div>

                            <div class="auth-system-table">
                                <div class="auth-system-table-head">
                                    <span>Cliente</span>
                                    <span>Status</span>
                                    <span>A&ccedil;&atilde;o</span>
                                </div>

                                <div class="auth-system-table-row">
                                    <strong>SP-4328336</strong>
                                    <span class="auth-system-status">Pendente</span>
                                    <span class="auth-system-action">Pegar</span>
                                </div>

                                <div class="auth-system-table-row">
                                    <strong>SP-4328328</strong>
                                    <span class="auth-system-status is-blue">Auditando</span>
                                    <span class="auth-system-action is-dark">Continuar</span>
                                </div>

                                <div class="auth-system-table-row">
                                    <strong>SP-4328307</strong>
                                    <span class="auth-system-status is-green">Finalizada</span>
                                    <span class="auth-system-action is-green">Ver</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="auth-system-card auth-system-card-left">
                    <strong>Hierarquia</strong>
                    <small>Cruzamento autom&aacute;tico por CPF do vendedor</small>
                </div>

                <div class="auth-system-card auth-system-card-right">
                    <strong>Produtos</strong>
                    <small>Cat&aacute;logo filtrado por tipo e per&iacute;odo</small>
                </div>
            </div>
        </div>

        <div class="auth-visual-metrics">
            <article>
                <strong>Importa&ccedil;&atilde;o recorrente</strong>
                <span>ZIP + CSV com deduplica&ccedil;&atilde;o por venda</span>
            </article>
            <article>
                <strong>Perfis de acesso</strong>
                <span>Administrador, Backoffice e Supervisor</span>
            </article>
            <article>
                <strong>Produtividade operacional</strong>
                <span>Fila, hierarquia e produtos no mesmo ambiente</span>
            </article>
        </div>
    </div>
</aside>
