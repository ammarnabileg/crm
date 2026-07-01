<?php

declare(strict_types=1);

/*
 * Database configuration (MySQL 8+). The connection itself is implemented in
 * Phase 8; this declares how it is configured. See docs/DATABASE_ARCHITECTURE.md.
 */

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'hahireai'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
        ],
    ],
];
