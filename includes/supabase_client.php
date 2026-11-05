<?php
declare(strict_types=1);

final class SupabaseApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}

final class SupabaseClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function isConfigured(): bool
    {
        return ($this->config['url'] ?? '') !== '' && ($this->config['anon_key'] ?? '') !== '';
    }

    public function authSignUp(string $email, string $password, array $metadata): array
    {
        return $this->request('POST', '/auth/v1/signup', [
            'email' => $email,
            'password' => $password,
            'data' => $metadata,
        ]);
    }

    public function authSignIn(string $email, string $password): array
    {
        return $this->request('POST', '/auth/v1/token?grant_type=password', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function authRefresh(string $refreshToken): array
    {
        return $this->request('POST', '/auth/v1/token?grant_type=refresh_token', [
            'refresh_token' => $refreshToken,
        ]);
    }

    public function authLogout(string $accessToken): void
    {
        $this->request('POST', '/auth/v1/logout', null, $accessToken);
    }

    public function usernameEmail(string $username): ?string
    {
        $serviceKey = $this->config['service_role_key'] ?? '';
        if ($serviceKey === '') {
            throw new SupabaseApiException('Username login is not configured.', 500);
        }

        $rows = $this->request(
            'GET',
            '/rest/v1/profiles?select=login_email&username=eq.' . rawurlencode($username) . '&limit=1',
            null,
            $serviceKey,
            ['Authorization: Bearer ' . $serviceKey]
        );
        return isset($rows[0]['login_email']) ? (string) $rows[0]['login_email'] : null;
    }

    public function phoneEmail(string $phone): ?string
    {
        $serviceKey = $this->config['service_role_key'] ?? '';
        if ($serviceKey === '') {
            throw new SupabaseApiException('Phone login is not configured.', 500);
        }

        $rows = $this->request(
            'GET',
            '/rest/v1/profiles?select=login_email&phone=eq.' . rawurlencode($phone) . '&limit=1',
            null,
            $serviceKey,
            ['Authorization: Bearer ' . $serviceKey]
        );
        return isset($rows[0]['login_email']) ? (string) $rows[0]['login_email'] : null;
    }

    public function select(string $table, array $query, string $jwt): array
    {
        return $this->request('GET', '/rest/v1/' . rawurlencode($table) . '?' . http_build_query($query), null, $jwt);
    }

    public function insert(string $table, array $data, string $jwt): array
    {
        return $this->request(
            'POST',
            '/rest/v1/' . rawurlencode($table),
            $data,
            $jwt,
            ['Prefer: return=representation']
        );
    }

    public function update(string $table, array $filters, array $data, string $jwt): array
    {
        return $this->request(
            'PATCH',
            '/rest/v1/' . rawurlencode($table) . '?' . http_build_query($filters),
            $data,
            $jwt,
            ['Prefer: return=representation']
        );
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $bearer = null,
        array $extraHeaders = []
    ): array {
        if (!$this->isConfigured()) {
            throw new SupabaseApiException('Supabase is not configured.', 503);
        }
        if (!function_exists('curl_init')) {
            throw new SupabaseApiException('The PHP cURL extension is required.', 500);
        }

        $url = rtrim($this->config['url'], '/') . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'apikey: ' . $this->config['anon_key'],
            'Authorization: Bearer ' . ($bearer ?: $this->config['anon_key']),
        ];
        $headers = array_merge($headers, $extraHeaders);

        $curl = curl_init($url);
        if ($curl === false) {
            throw new SupabaseApiException('Failed to initialize cURL session.', 500);
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
            // Bypass SSL verification issues on local Windows dev servers
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $curlError = curl_error($curl);
            error_log('Supabase cURL error: ' . $curlError);
            // Show the exact cURL error during debugging instead of generic message
            throw new SupabaseApiException('cURL Connection Error: ' . ($curlError !== '' ? $curlError : 'Unknown network failure'), 503);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            error_log('Supabase API error (' . $status . '): ' . substr($raw, 0, 500));
            $message = (string) ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? 'Request failed.');
            throw new SupabaseApiException($message, $status, $decoded);
        }

        return $decoded;
    }
}