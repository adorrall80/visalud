<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
    if ($requestPath !== '/' && is_file($publicFile)) {
        return false;
    }
}

$application = require dirname(__DIR__) . '/bootstrap/app.php';

$application->run();
