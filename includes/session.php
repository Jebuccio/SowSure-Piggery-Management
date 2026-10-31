<?php
declare(strict_types=1);

function start_secure_session(array $app): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) $app['session_absolute_seconds']);

    session_name('SOWSURE_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    $_SESSION['started_at'] ??= $now;
    $_SESSION['last_activity'] ??= $now;

    if (
        $now - (int) $_SESSION['last_activity'] > $app['session_idle_seconds']
        || $now - (int) $_SESSION['started_at'] > $app['session_absolute_seconds']
    ) {
        clear_session();
        session_start();
        $_SESSION['flash']['error'] = 'Your session expired. Please sign in again.';
    }

    $_SESSION['last_activity'] = $now;
}

function clear_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
