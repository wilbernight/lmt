<?php

return [
    // Site administrators must have two-factor login enabled to open the admin panel.
    'admin_requires_2fa' => (bool) env('ADMIN_REQUIRES_2FA', env('APP_ENV') === 'production'),

    /*
     * Encryption at rest for business data (projects, tasks, customers, letters,
     * chat, notes, files...). A separate key from APP_KEY, never stored in the
     * database. Back it up: without it the encrypted data cannot be read.
     *
     * Generate: php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
     * Rotate:   move the old key to DATA_ENCRYPTION_PREVIOUS_KEYS (comma separated),
     *           set a new DATA_ENCRYPTION_KEY, then run: php artisan app:reencrypt
     */
    'data_key' => env('DATA_ENCRYPTION_KEY'),
    'data_previous_keys' => array_filter(explode(',', (string) env('DATA_ENCRYPTION_PREVIOUS_KEYS', ''))),
    'data_cipher' => 'aes-256-gcm',
];
