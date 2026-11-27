<?php
declare(strict_types=1);

function load_environment(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

load_environment(dirname(__DIR__) . '/.env');

function env_value(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

$timezone = env_value('APP_TIMEZONE', 'Asia/Manila') ?: 'Asia/Manila';
date_default_timezone_set($timezone);

return [
    'name' => 'SowSure',
    'env' => env_value('APP_ENV', 'production'),
    'url' => rtrim(env_value('APP_URL', 'http://localhost:8000') ?: '', '/'),
    'timezone' => $timezone,
    'session_idle_seconds' => (int) env_value('SESSION_IDLE_SECONDS', '7200'),
    'session_absolute_seconds' => (int) env_value('SESSION_ABSOLUTE_SECONDS', '43200'),
];
