<?php
declare(strict_types=1);

function is_authenticated(): bool
{
    return isset($_SESSION['auth']['user_id'], $_SESSION['auth']['access_token']);
}

function auth_user_id(): string
{
    return (string) ($_SESSION['auth']['user_id'] ?? '');
}

function access_token(): string
{
    return (string) ($_SESSION['auth']['access_token'] ?? '');
}

function store_auth_session(array $response): void
{
    if (empty($response['access_token']) || empty($response['user']['id'])) {
        throw new RuntimeException('Authentication response was incomplete.');
    }
    session_regenerate_id(true);
    $_SESSION['started_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['auth'] = [
        'user_id' => (string) $response['user']['id'],
        'email' => (string) ($response['user']['email'] ?? ''),
        'access_token' => (string) $response['access_token'],
        'refresh_token' => (string) ($response['refresh_token'] ?? ''),
        'expires_at' => time() + (int) ($response['expires_in'] ?? 3600),
    ];
    unset($_SESSION['profile']);
}

function require_auth(): void
{
    global $supabase;
    if (!is_authenticated()) {
        flash('error', 'Please sign in to continue.');
        redirect('/login.php');
    }

    if ((int) ($_SESSION['auth']['expires_at'] ?? 0) <= time() + 60) {
        try {
            $refresh = (string) ($_SESSION['auth']['refresh_token'] ?? '');
            if ($refresh === '') {
                throw new RuntimeException('Missing refresh token.');
            }
            store_auth_session($supabase->authRefresh($refresh));
        } catch (Throwable $exception) {
            error_log('Session refresh failed: ' . $exception->getMessage());
            clear_session();
            session_start();
            flash('error', 'Your session expired. Please sign in again.');
            redirect('/login.php');
        }
    }
}

function current_profile(): array
{
    global $supabase;
    if (!is_authenticated()) {
        return [];
    }
    if (isset($_SESSION['profile']) && is_array($_SESSION['profile'])) {
        return $_SESSION['profile'];
    }
    try {
        $rows = $supabase->select('profiles', [
            'select' => 'id,full_name,farm_name,username,preferred_language,created_at',
            'id' => 'eq.' . auth_user_id(),
            'limit' => '1',
        ], access_token());
        $_SESSION['profile'] = $rows[0] ?? [];
    } catch (Throwable $exception) {
        error_log('Profile fetch failed: ' . $exception->getMessage());
        $_SESSION['profile'] = [];
    }
    return $_SESSION['profile'];
}

function login_is_rate_limited(): bool
{
    $window = 15 * 60;
    $attempts = array_values(array_filter(
        $_SESSION['login_attempts'] ?? [],
        static fn (int $timestamp): bool => $timestamp > time() - $window
    ));
    $_SESSION['login_attempts'] = $attempts;
    return count($attempts) >= 5;
}

function record_failed_login(): void
{
    $_SESSION['login_attempts'][] = time();
}

function clear_login_attempts(): void
{
    unset($_SESSION['login_attempts']);
}
