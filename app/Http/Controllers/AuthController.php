<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\GoogleAuthService;

final class AuthController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly GoogleAuthService $googleAuth,
    ) {
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('auth/login', [
            'title' => 'Acceso',
        ], 'layouts/guest'));
    }

    public function redirectToGoogle(Request $request): Response
    {
        return Response::redirect($this->googleAuth->authorizationUrl());
    }

    public function handleGoogleCallback(Request $request): Response
    {
        $error = trim((string) $request->input('error', ''));
        if ($error !== '') {
            $this->session->flash('error', 'El acceso con Google fue cancelado.');
            return Response::redirect($this->afterGoogleLocation('/login'));
        }

        $code = trim((string) $request->input('code', ''));
        $state = trim((string) $request->input('state', ''));
        if ($code === '' || $state === '') {
            $this->session->flash('error', 'Google devolvió una respuesta incompleta.');
            return Response::redirect($this->afterGoogleLocation('/login'));
        }

        try {
            $userId = $this->googleAuth->authenticate($code, $state);
            $this->googleAuth->establishSession($userId);
            return Response::redirect($this->afterGoogleLocation('/'));
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
            return Response::redirect($this->afterGoogleLocation('/login'));
        }
    }

    public function destroy(Request $request): Response
    {
        $this->session->invalidate();
        return Response::redirect('/login');
    }

    private function afterGoogleLocation(string $default): string
    {
        return $this->session->get('invitacion_token_pendiente') ? '/invitaciones/aceptar' : $default;
    }
}
