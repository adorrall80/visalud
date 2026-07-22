<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Env;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    throw new RuntimeException('Ejecute composer install antes de iniciar la aplicación.');
}

require $autoload;

Env::load($rootPath . '/.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Santiago'));

return new Application($rootPath);
