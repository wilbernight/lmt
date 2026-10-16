<?php

return [
    // Site administrators must have two-factor login enabled to open the admin panel.
    'admin_requires_2fa' => (bool) env('ADMIN_REQUIRES_2FA', env('APP_ENV') === 'production'),
];
