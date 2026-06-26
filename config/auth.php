<?php

declare(strict_types=1);

return [
    // Session key under which the authenticated user id is stored.
    'session_key' => 'auth_user_id',

    // Session key under which the active tenant (company) id is stored.
    'tenant_key' => 'active_company_id',

    // Password reset token lifetime (minutes).
    'reset_token_ttl' => 60,

    // Login throttling.
    'max_login_attempts' => 5,
    'lockout_seconds'    => 900,

    // Roles considered platform-level (not bound to a single tenant).
    'super_admin_role' => 'super-admin',
];
