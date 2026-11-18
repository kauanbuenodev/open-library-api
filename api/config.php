<?php

declare(strict_types=1);

return [
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: '',
        'issuer' => 'open-library-trabalho',
        'audience' => 'open-library-api',
        'ttl_seconds' => 3600,
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'open_library_app',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'open_library' => [
        'search_url' => 'https://openlibrary.org/search.json',
        'user_agent' => getenv('OPEN_LIBRARY_USER_AGENT')
            ?: 'TrabalhoOpenLibraryUNIFEG/1.0 (contato: grupo6@example.com)',
        'connect_timeout_seconds' => 10,
        'timeout_seconds' => 20,
    ],
];
