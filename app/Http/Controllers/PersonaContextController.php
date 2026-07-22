<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PersonaContext;

final class PersonaContextController
{
    public function __construct(
        private readonly PersonaContext $context,
        private readonly Session $session,
    ) {
    }

    public function select(Request $request): Response
    {
        $personId = (int) $request->input('persona_id', 0);
        $returnTo = (string) $request->input('return_to', '/');
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, "\n")) {
            $returnTo = '/';
        }
        try {
            $person = $this->context->select($personId);
            $this->session->flash('success', 'Ahora estás trabajando con ' . $person['nombre'] . '.');
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('error', $exception->getMessage());
            $returnTo = '/personas';
        }
        return Response::redirect($returnTo);
    }
}
