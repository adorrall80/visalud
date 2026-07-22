<?php

use App\Http\Controllers\AuthController;

$router->get('/login', [AuthController::class, 'create']);
$router->get('/auth/google', [AuthController::class, 'redirectToGoogle']);
$router->get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
$router->post('/logout', [AuthController::class, 'destroy'])->middleware(['auth', 'csrf']);
