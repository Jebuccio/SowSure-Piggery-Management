<?php
declare(strict_types=1);

// Disable direct error display in output to keep JSON response clean
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Load bootstrap FIRST to initialize SOWSURE_SESSION and $supabase correctly
$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$accessToken = $data['access_token'] ?? $_POST['access_token'] ?? null;
$refreshToken = $data['refresh_token'] ?? $_POST['refresh_token'] ?? '';

if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No access token provided']);
    exit;
}

try {
    // Decode the Supabase JWT access token to extract user ID and email securely
    $user = [];
    $jwtParts = explode('.', (string) $accessToken);
    if (count($jwtParts) === 3) {
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwtParts[1]));
        $payload = json_decode($payloadJson, true);
        if (is_array($payload)) {
            $user = [
                'id' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null,
                'user_metadata' => $payload['user_metadata'] ?? []
            ];
        }
    }

    if (empty($user['id'])) {
        throw new RuntimeException('Invalid or unreadable access token payload.');
    }

    // Build the complete response structure that store_auth_session() expects
    $responsePayload = [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'user' => $user
    ];

    // Store the auth session in SOWSURE_SESSION
    store_auth_session($responsePayload);

    // Auto-provision profile row if it doesn't exist
    current_profile();

    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;

} catch (Throwable $e) {
    error_log('Auth callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}