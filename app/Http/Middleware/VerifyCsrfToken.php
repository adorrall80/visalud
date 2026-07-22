<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if (!$this->session->tokenIsValid((string) $request->input('_token'))) {
            return Response::html('<h1>419</h1><p>La sesión del formulario expiró.</p>', 419);
        }
        return $next($request);
    }
}
