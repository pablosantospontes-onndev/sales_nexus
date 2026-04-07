<?php
$pageTitle = (string) ($title ?? '');

if (str_contains($pageTitle, 'Usu') && str_contains($pageTitle, 'rios') && str_contains($pageTitle, 'Ã')) {
    $pageTitle = html_entity_decode('Usu&aacute;rios', ENT_QUOTES, 'UTF-8');
}
$sidebarIcons = [
    'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4.75 4.75h6.75v6.75H4.75Zm7.75 0h6.75v9.75H12.5Zm-7.75 7.75h6.75v6.75H4.75Zm7.75 2.25h6.75v4.5H12.5Z"/></svg>',
    'import' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M11.25 4.5h1.5v8.69l2.72-2.72 1.06 1.06L12 16.06l-4.53-4.53 1.06-1.06 2.72 2.72ZM5.25 18h13.5v1.5H5.25Z"/></svg>',
    'queue' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5.25 5.25h13.5v3H5.25Zm0 5.25h13.5v3H5.25Zm0 5.25h13.5v3H5.25Z"/></svg>',
    'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5.25 18.75h13.5M7.5 15.75V10.5M12 15.75V6.75M16.5 15.75v-3.75" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12a3.75 3.75 0 1 0-3.75-3.75A3.75 3.75 0 0 0 12 12Zm-6.75 7.5a6.75 6.75 0 0 1 13.5 0h-1.5a5.25 5.25 0 0 0-10.5 0Zm11.45-8.43a3 3 0 1 0-1.53-5.58 5.38 5.38 0 0 1 0 4.71 3 3 0 0 0 1.53.87Z"/></svg>',
    'products' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3.82 7.5 3.75v8.86L12 20.18l-7.5-3.75V7.57Zm0 1.68L6.2 8.4 12 11.3l5.8-2.9ZM5.25 9.62v5.89l6 3v-5.89Zm13.5 0-6 3v5.89l6-3Z"/></svg>',
    'hierarchy' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10.5 4.5a2.25 2.25 0 1 0-4.5 0 2.25 2.25 0 0 0 4.5 0Zm7.5 0a2.25 2.25 0 1 0-4.5 0 2.25 2.25 0 0 0 4.5 0ZM14.25 18a2.25 2.25 0 1 0-4.5 0 2.25 2.25 0 0 0 4.5 0ZM8.25 8.25v3.05A2.25 2.25 0 0 0 10.5 13.56h3a2.25 2.25 0 0 0 2.25-2.26V8.25h-1.5v3.05a.75.75 0 0 1-.75.76h-3a.75.75 0 0 1-.75-.76V8.25Zm3 5.81V15h1.5v-.94Z"/></svg>',
];

$sidebarNavItems = [
    [
        'href' => url('dashboard'),
        'label' => 'Dashboard',
        'icon' => 'dashboard',
        'active' => current_route() === 'dashboard',
    ],
    [
        'href' => url('import'),
        'label' => 'Importar PAP',
        'icon' => 'import',
        'active' => current_route() === 'import',
    ],
    [
        'href' => url('queue'),
        'label' => 'Fila de auditoria',
        'icon' => 'queue',
        'active' => str_starts_with(current_route(), 'queue'),
    ],
];

if (($authUser['role'] ?? '') === 'ADMINISTRADOR') {
    $sidebarNavItems[] = [
        'href' => url('reports'),
        'label' => 'Relatórios',
        'icon' => 'reports',
        'active' => str_starts_with(current_route(), 'reports'),
    ];
    $sidebarNavItems[] = [
        'href' => url('users'),
        'label' => html_entity_decode('Usu&aacute;rios', ENT_QUOTES, 'UTF-8'),
        'icon' => 'users',
        'active' => str_starts_with(current_route(), 'users'),
    ];
    $sidebarNavItems[] = [
        'href' => url('products'),
        'label' => 'Produtos',
        'icon' => 'products',
        'active' => str_starts_with(current_route(), 'products'),
    ];
    $sidebarNavItems[] = [
        'href' => url('hierarchy'),
        'label' => 'Hierarquia',
        'icon' => 'hierarchy',
        'active' => str_starts_with(current_route(), 'hierarchy'),
    ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(config('name')) ?></title>
    <script>
        try {
            if (window.localStorage.getItem('vigg-nexus-theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        } catch (error) {
            // Ignore localStorage issues and keep the default theme.
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="app-shell">
<?php
$sidebarLogoLight = APP_ROOT . '/assets/nexuspgi_light.png';
$sidebarLogoDark = APP_ROOT . '/assets/nexuspgi_darkmode.png';
$sidebarMiniBadge = APP_ROOT . '/assets/sales_nexus.png';
?>
    <div class="app-backdrop"></div>
    <aside class="sidebar">
        <div class="sidebar-main">
            <div class="sidebar-brand">
                <div class="sidebar-brand-top">
                <button
                    type="button"
                    class="sidebar-mini-toggle"
                    data-sidebar-mini-toggle
                    aria-expanded="true"
                        aria-label="Recolher menu"
                        data-ui-tooltip="Recolher menu"
                    >
                        <span class="sidebar-mini-toggle-content" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="sidebar-mini-toggle-icon" focusable="false">
                                <path d="M17 6.5 12 12l5 5.5"></path>
                                <path d="M11.5 6.5 6.5 12l5 5.5"></path>
                            </svg>
                        <?php if (is_file($sidebarMiniBadge)): ?>
                            <img src="assets/sales_nexus.png" alt="" class="sidebar-mini-toggle-badge">
                        <?php endif; ?>
                        <svg viewBox="0 0 24 24" class="sidebar-mini-toggle-expand-icon" focusable="false">
                            <path d="M6.5 6.5 11.5 12l-5 5.5"></path>
                            <path d="M12 6.5 17 12l-5 5.5"></path>
                        </svg>
                    </span>
                </button>
            </div>
                <div class="sidebar-brand-block">
                    <?php if (is_file($sidebarLogoLight) || is_file($sidebarLogoDark)): ?>
                        <?php if (is_file($sidebarLogoLight)): ?>
                            <img src="assets/nexuspgi_light.png" alt="Sales Nexus" class="sidebar-logo sidebar-logo-light">
                        <?php endif; ?>
                        <?php if (is_file($sidebarLogoDark)): ?>
                            <img src="assets/nexuspgi_darkmode.png" alt="Sales Nexus" class="sidebar-logo sidebar-logo-dark">
                        <?php endif; ?>
                    <?php else: ?>
                        <h1 class="sidebar-brand-fallback">SALES NEXUS</h1>
                    <?php endif; ?>
                    <p class="sidebar-copy">VIGGO CRM</p>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Principal">
                <?php foreach ($sidebarNavItems as $item): ?>
                    <a href="<?= e($item['href']) ?>" class="<?= $item['active'] ? 'is-active' : '' ?>">
                        <span class="sidebar-nav-icon" aria-hidden="true"><?= $sidebarIcons[$item['icon']] ?? '' ?></span>
                        <span class="sidebar-nav-label"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-theme-row">
                <span class="sidebar-theme-label">Modo noturno</span>
                <button
                    type="button"
                    class="theme-switch theme-switch-compact"
                    data-theme-toggle
                    role="switch"
                    aria-checked="false"
                    aria-label="Ativar tema escuro"
                    data-ui-tooltip="Ativar tema escuro"
                >
                    <span class="theme-switch-track">
                        <span class="theme-switch-thumb" aria-hidden="true"></span>
                        <span class="theme-switch-state theme-switch-state-off" aria-hidden="true">OFF</span>
                        <span class="theme-switch-state theme-switch-state-on" aria-hidden="true">ON</span>
                    </span>
                </button>
            </div>
            <div class="sidebar-user-card">
                <div class="sidebar-user-meta">
                    <strong><?= e($authUser['name'] ?? '-') ?></strong>
                    <span><?= e($authUser['role'] ?? '-') ?></span>
                </div>
                <form method="post" action="<?= e(url('logout')) ?>" class="sidebar-logout-form">
                    <?= \App\Core\Csrf::input() ?>
                    <button type="submit" class="ghost-button sidebar-logout-button" aria-label="Sair" data-ui-tooltip="Sair">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M10.5 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h3.75"></path>
                            <path d="M15 8.25 19.5 12 15 15.75"></path>
                            <path d="M19.5 12H9"></path>
                        </svg>
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="page">
        <header class="page-header">
            <div class="page-header-inner">
                <div>
                    <p class="eyebrow">Sales Nexus</p>
                    <h2><?= e($pageTitle) ?></h2>
                </div>

                <button type="button" class="secondary-button sidebar-toggle-button" data-sidebar-toggle>
                    Ocultar menu
                </button>
            </div>
        </header>

        <?php foreach ($flashes as $type => $message): ?>
            <div class="flash flash-<?= e($type) ?>"><?= e($message) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <div class="copy-toast" data-copy-toast aria-live="polite" role="status"></div>
    <script src="assets/app.js"></script>
</body>
</html>
