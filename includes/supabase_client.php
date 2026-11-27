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

    public function authSignUp(string $email, string $password, array $metadata, ?string $redirectTo = null): array
    {
        $path = '/auth/v1/signup';
        if ($redirectTo !== null && $redirectTo !== '') {
            $path .= '?redirect_to=' . rawurlencode($redirectTo);
        }

        return $this->request('POST', $path, [
            'email' => $email,
            'password' => $password,
            'data' => $metadata,
        ]);
    }

    public function authResendVerification(string $email, ?string $redirectTo = null): array
    {
        $path = '/auth/v1/resend';
        if ($redirectTo !== null && $redirectTo !== '') {
            $path .= '?redirect_to=' . rawurlencode($redirectTo);
        }

        return $this->request('POST', $path, [
            'type' => 'signup',
            'email' => $email,
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
            $serviceKey
        );
        return isset($rows[0]['login_email']) ? (string) $rows[0]['login_email'] : null;
    }

    public function phoneEmail(string $phone): ?string
    {
        $serviceKey = $this->config['service_role_key'] ?? '';
        if ($serviceKey === '') {
            throw new SupabaseApiException('Phone login is not configured.', 500);
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $candidates = [$phone, $digits];
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $candidates[] = '+63' . substr($digits, 1);
            $candidates[] = '63' . substr($digits, 1);
        } elseif (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $candidates[] = '+' . $digits;
            $candidates[] = '0' . substr($digits, 2);
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $candidate) {
            $rows = $this->request(
                'GET',
                '/rest/v1/profiles?select=login_email&phone=eq.' . rawurlencode($candidate) . '&limit=1',
                null,
                $serviceKey
            );
            if (!empty($rows[0]['login_email'])) {
                return (string) $rows[0]['login_email'];
            }
        }

        return null;
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

    public function delete(string $table, array $filters, string $jwt): array
    {
        return $this->request(
            'DELETE',
            '/rest/v1/' . rawurlencode($table) . '?' . http_build_query($filters),
            null,
            $jwt,
            ['Prefer: return=representation']
        );
    }

    public function uploadStorageFile(
        string $bucket,
        string $objectPath,
        string $localFilePath,
        string $mimeType,
        string $jwt
    ): string {
        if (!$this->isConfigured()) {
            throw new SupabaseApiException('Supabase is not configured.', 503);
        }
        if (!is_file($localFilePath) || !is_readable($localFilePath)) {
            throw new SupabaseApiException('Uploaded temporary file is unreadable.', 400);
        }

        $binary = file_get_contents($localFilePath);
        if ($binary === false) {
            throw new SupabaseApiException('Failed to read uploaded file bytes.', 500);
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($objectPath, '/'))));
        $baseUrl = rtrim($this->config['url'], '/');
        $uploadUrl = $baseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . $encodedPath;

        $curl = curl_init($uploadUrl);
        if ($curl === false) {
            throw new SupabaseApiException('Failed to initialize storage upload session.', 500);
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->config['anon_key'],
                'Authorization: Bearer ' . $jwt,
                'Content-Type: ' . $mimeType,
                'x-upsert: true',
            ],
            CURLOPT_POSTFIELDS => $binary,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $curlError = curl_error($curl);
            throw new SupabaseApiException('Storage cURL error: ' . ($curlError !== '' ? $curlError : 'Unknown failure'), 503);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            error_log('Supabase storage upload error (' . $status . '): ' . substr($raw, 0, 500));
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'Storage upload failed.');
            throw new SupabaseApiException($message, $status, $decoded);
        }

        return $baseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . $encodedPath;
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
            throw new SupabaseApiException('cURL Connection Error: ' . ($curlError !== '' ? $curlError : 'Unknown network failure'), 503);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            error_log('Supabase failed request: ' . $method . ' ' . $path);
            error_log('Supabase API error (' . $status . '): ' . substr($raw, 0, 500));
            $message = (string) ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? 'Request failed.');
            throw new SupabaseApiException($message, $status, $decoded);
        }

        return $decoded;
    }
}