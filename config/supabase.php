<?php
declare(strict_types=1);

return [
    'url' => rtrim(env_value('SUPABASE_URL', '') ?: '', '/'),
    'anon_key' => env_value('SUPABASE_ANON_KEY', '') ?: '',
    'service_role_key' => env_value('SUPABASE_SERVICE_ROLE_KEY', '') ?: '',
];
