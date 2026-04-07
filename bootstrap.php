<?php

declare(strict_types=1);

define('APP_ROOT', __DIR__);

$GLOBALS['config'] = require APP_ROOT . '/config/app.php';

if (PHP_SAPI === 'cli') {
    $sessionPath = APP_ROOT . '/storage/sessions';

    if (! is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }

    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/helpers.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'America/Sao_Paulo');
