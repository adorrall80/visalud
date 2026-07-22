<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FamiliaService;

final class AuthorizeAdministrator implements Middleware
{
    public function __construct(
        private readonly Session $session,
        private readonly FamiliaService $families,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            $this->families->requireAdministrator(
                (int) $this->session->get('family_id'),
                (int) $this->session->get('user_id'),
            );
        } catch (\RuntimeException) {
            return Response::html('<h1>403</h1><p>Esta sección requiere el rol de administrador.</p>', 403);
        }
        return $next($request);
    }
}
