<?php

return [
    'name' => env('APP_NAME', 'Portal Familiar de Salud'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'America/Santiago'),
    'force_https' => filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),
];
