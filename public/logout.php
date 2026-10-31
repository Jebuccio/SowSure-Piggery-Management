<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!method_is('POST')) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
if (is_authenticated()) {
    try {
        $supabase->authLogout(access_token());
    } catch (Throwable $exception) {
        error_log('Remote logout failed: ' . $exception->getMessage());
    }
}
clear_session();
session_start();
flash('success', 'You have signed out safely.');
redirect('/login.php');
