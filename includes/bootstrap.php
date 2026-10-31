<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/config/app.php';
$supabaseConfig = require dirname(__DIR__) . '/config/supabase.php';

require_once __DIR__ . '/session.php';
start_secure_session($app);
$translations = require dirname(__DIR__) . '/lang/en.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function t(string $key): string
{
    global $translations;
    return (string) ($translations[$key] ?? $key);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function pull_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function method_is(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($method);
}

function format_date(?string $date, string $format = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($date))->format($format);
    } catch (Throwable) {
        return '—';
    }
}

function nav_icon(string $name): string
{
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z"/><path d="M9 21v-7h6v7"/>',
        'sow' => '<path d="M5 11c0-3.3 3.1-6 7-6s7 2.7 7 6v2.5a4.5 4.5 0 0 1-4.5 4.5h-5A4.5 4.5 0 0 1 5 13.5V11Z"/><path d="M8 7 5 4v5M16 7l3-3v5M9 18v2m6-2v2M9 12h.01M15 12h.01"/><path d="M10 15h4"/>',
        'breeding' => '<circle cx="8" cy="8" r="4"/><circle cx="16" cy="16" r="4"/><path d="m11 11 2 2M8 12v4m-2-2h4m6-2V8m-2 2h4"/>',
        'care' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M9 12h6m-3-3v6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function friendly_data_error(Throwable $exception, string $fallback): string
{
    if ($exception instanceof SupabaseApiException) {
        $code = (string) ($exception->details['code'] ?? '');
        if ($code === '23505') {
            return 'That value is already in use. Please choose another.';
        }
        if ($code === '22023' || $code === '23514' || $code === 'P0001') {
            return 'Some information was not accepted. Review the form and try again.';
        }
    }
    return $fallback;
}

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/supabase_client.php';
$supabase = new SupabaseClient($supabaseConfig);
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';
