<?php

declare(strict_types=1);

use App\Core\Auth;

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function url(?string $route = null, array $params = []): string
{
    if ($route !== null && $route !== '') {
        $params = ['route' => $route] + $params;
    }

    $query = http_build_query(array_filter(
        $params,
        static fn (mixed $value): bool => $value !== null && $value !== ''
    ));

    return 'index.php' . ($query !== '' ? '?' . $query : '');
}

function redirect(?string $route = null, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, ?string $message = null): mixed
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);

    return $value;
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

function render(string $view, array $data = [], string $layout = 'app'): void
{
    $layoutFile = APP_ROOT . '/views/layouts/' . $layout . '.php';

    if (! is_file($layoutFile)) {
        throw new RuntimeException("Layout not found: {$layout}");
    }

    $data['authUser'] = Auth::user();
    $data['flashes'] = $data['flashes'] ?? consume_flashes();
    $data['title'] = $data['title'] ?? config('name');
    $content = view($view, $data);

    extract($data, EXTR_SKIP);

    require $layoutFile;
}

function view(string $view, array $data = []): string
{
    $viewFile = APP_ROOT . '/views/' . $view . '.php';

    if (! is_file($viewFile)) {
        throw new RuntimeException("View not found: {$view}");
    }

    $data['authUser'] = $data['authUser'] ?? Auth::user();

    extract($data, EXTR_SKIP);

    ob_start();
    require $viewFile;

    return (string) ob_get_clean();
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function normalize_text(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = str_replace(["\xC2\xA0", "\xA0"], ' ', $value);
    $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

    return trim((string) ($converted !== false ? $converted : $value));
}

function ascii_key(?string $value): string
{
    $value = normalize_text($value);
    $value = strtr($value, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ç' => 'C', 'ç' => 'c',
        'Ñ' => 'N', 'ñ' => 'n',
    ]);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $transliterated = $transliterated !== false ? $transliterated : $value;
    $transliterated = strtolower($transliterated);
    $transliterated = str_replace(["'", '`', '´'], '', $transliterated);
    $transliterated = preg_replace('/\s+/', ' ', $transliterated) ?? $transliterated;

    return trim($transliterated);
}

function clean_document(?string $value): string
{
    return preg_replace('/\D+/', '', normalize_text($value)) ?? '';
}

function valid_cpf(?string $value): bool
{
    $cpf = clean_document($value);

    if (strlen($cpf) !== 11) {
        return false;
    }

    if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
        return false;
    }

    for ($digitIndex = 9; $digitIndex < 11; $digitIndex++) {
        $sum = 0;

        for ($position = 0; $position < $digitIndex; $position++) {
            $sum += (int) $cpf[$position] * (($digitIndex + 1) - $position);
        }

        $remainder = ($sum * 10) % 11;
        $checkDigit = $remainder === 10 ? 0 : $remainder;

        if ($checkDigit !== (int) $cpf[$digitIndex]) {
            return false;
        }
    }

    return true;
}

function password_policy_checks(?string $password, ?string $confirmation = null): array
{
    $password = (string) ($password ?? '');
    $confirmation = $confirmation !== null ? (string) $confirmation : null;

    return [
        'length' => mb_strlen($password) >= 6,
        'uppercase' => preg_match('/\p{Lu}/u', $password) === 1,
        'special' => preg_match('/[^\p{L}\p{N}]/u', $password) === 1,
        'match' => $confirmation !== null && $password !== '' && $password === $confirmation,
    ];
}

function strong_password_error(?string $password, ?string $confirmation = null): ?string
{
    $password = (string) ($password ?? '');
    $checks = password_policy_checks($password, $confirmation);

    if ($password === '') {
        return 'Informe a nova senha.';
    }

    if (! $checks['length']) {
        return 'A senha precisa ter no mínimo 6 caracteres.';
    }

    if (! $checks['uppercase']) {
        return 'A senha precisa ter ao menos 1 letra maiúscula.';
    }

    if (! $checks['special']) {
        return 'A senha precisa ter ao menos 1 caractere especial.';
    }

    if ($confirmation !== null && ! $checks['match']) {
        return 'A confirmação da senha não confere.';
    }

    return null;
}

function normalize_date_to_db(?string $value): ?string
{
    $value = normalize_text($value);
    if ($value === '') {
        return null;
    }

    $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
}

function normalize_time_to_db(?string $value): ?string
{
    $value = normalize_text($value);
    if ($value === '') {
        return null;
    }

    foreach (['H:i:s', 'H:i'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value);
        if ($time !== false) {
            return $time->format('H:i:s');
        }
    }

    return null;
}

function format_date_br(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function format_currency_br(?float $value): string
{
    return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
}

function format_minutes_human(?float $value): string
{
    if ($value === null) {
        return '-';
    }

    $minutes = max(0, (int) round($value));

    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($remainingMinutes === 0) {
        return $hours . 'h';
    }

    return $hours . 'h ' . $remainingMinutes . 'min';
}

function format_datetime_br(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

function current_route(): string
{
    return (string) ($_GET['route'] ?? '');
}
