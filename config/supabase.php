<?php
declare(strict_types=1);

$supabaseUrl = env_value('SUPABASE_URL', '') ?: '';
$supabaseKey = env_value('SUPABASE_ANON_KEY', '') ?: env_value('SUPABASE_SERVICE_ROLE_KEY', '') ?: '';

if ($supabaseUrl === '' || $supabaseKey === '') {
    throw new RuntimeException('Supabase is not configured. Please check your .env file for SUPABASE_URL and SUPABASE_ANON_KEY.');
}

return [
    'url' => rtrim($supabaseUrl, '/'),
    'key' => $supabaseKey,
    'anon_key' => env_value('SUPABASE_ANON_KEY', '') ?: '',
    'service_role_key' => env_value('SUPABASE_SERVICE_ROLE_KEY', '') ?: '',
];