<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class Authenticate implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->session->get('user_id') === null) {
            $this->session->flash('error', 'Debes iniciar sesión para continuar.');
            return Response::redirect('/login');
        }
        return $next($request);
    }
}
