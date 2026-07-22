<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FamiliaService;

final class AuthorizeFamily implements Middleware
{
    public function __construct(
        private readonly Session $session,
        private readonly FamiliaService $families,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $familyId = (int) $this->session->get('family_id', 0);
        $userId = (int) $this->session->get('user_id', 0);
        if ($familyId === 0 || $userId === 0) {
            return Response::redirect('/familias/create');
        }
        if ($this->families->findForUser($familyId, $userId) === null) {
            $this->session->forget('family_id');
            return Response::html('<h1>403</h1><p>No tienes acceso a esta familia.</p>', 403);
        }
        return $next($request);
    }
}
