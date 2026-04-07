<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e(config('name')) ?></title>
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
<body class="auth-shell">
    <main class="auth-stage">
        <?php if (($authInlineFlashes ?? false) !== true && $flashes !== []): ?>
            <div class="auth-flash-stack">
                <?php foreach ($flashes as $type => $message): ?>
                    <div class="flash flash-<?= e($type) ?>"><?= e($message) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <script src="assets/app.js"></script>
</body>
</html>
