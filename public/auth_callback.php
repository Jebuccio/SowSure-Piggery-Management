<?php
declare(strict_types=1);

ob_start();
require dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['access_token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No access token provided']);
    exit;
}

try {
    $accessToken = $data['access_token'];
    $refreshToken = $data['refresh_token'] ?? '';

    $_SESSION['access_token'] = $accessToken;
    $_SESSION['refresh_token'] = $refreshToken;
    $_SESSION['authenticated'] = true;

    if (function_exists('store_auth_session')) {
        store_auth_session([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;

} catch (Throwable $e) {
    error_log('Auth callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}