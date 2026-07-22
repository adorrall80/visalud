<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\InvitacionService;
use App\Services\PersonaContext;

final class InvitacionController
{
    private const SESSION_TOKEN = 'invitacion_token_pendiente';

    public function __construct(
        private readonly InvitacionService $invitations,
        private readonly Session $session,
        private readonly View $view,
        private readonly PersonaContext $personContext,
    ) {
    }

    public function create(Request $request): Response
    {
        try {
            $invitation = $this->invitations->create(
                (int) $this->session->get('family_id'),
                (int) $this->session->get('user_id'),
            );
            $baseUrl = rtrim((string) env('APP_URL', 'http://localhost:8000'), '/');
            $this->session->flash('invitacion_generada', [
                ...$invitation,
                'url' => $baseUrl . '/invitaciones/aceptar?token=' . rawurlencode($invitation['token']),
            ]);
            $this->session->flash('success', 'Enlace generado. Cópialo antes de abandonar esta pantalla.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias/integrantes');
    }

    public function revoke(Request $request, string $id): Response
    {
        try {
            $this->invitations->revoke(
                (int) $this->session->get('family_id'),
                (int) $id,
                (int) $this->session->get('user_id'),
            );
            $this->session->flash('success', 'Invitación revocada correctamente.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias/integrantes');
    }

    public function show(Request $request): Response
    {
        $provided = trim((string) $request->input('token', ''));
        if ($provided !== '') {
            $this->session->put(self::SESSION_TOKEN, $provided);
        }
        $token = (string) $this->session->get(self::SESSION_TOKEN, '');
        try {
            $invitation = $this->invitations->preview($token);
            $userId = (int) $this->session->get('user_id', 0);
            return Response::html($this->view->render('invitaciones/show', [
                'title' => 'Invitación familiar',
                'invitacion' => $invitation,
                'usuario' => $userId > 0 ? $this->invitations->user($userId) : null,
                'error' => $this->view->flash('error'),
            ], 'layouts/guest'));
        } catch (\Throwable $exception) {
            $this->session->forget(self::SESSION_TOKEN);
            return Response::html($this->view->render('invitaciones/invalid', [
                'title' => 'Invitación no disponible',
                'message' => $exception->getMessage(),
            ], 'layouts/guest'), 410);
        }
    }

    public function accept(Request $request): Response
    {
        $token = (string) $this->session->get(self::SESSION_TOKEN, '');
        try {
            $result = $this->invitations->accept($token, (int) $this->session->get('user_id'));
            $this->session->put('family_id', $result['familia_id']);
            $this->session->forget(self::SESSION_TOKEN);
            $this->personContext->clear();
            $this->session->flash('success', $result['ya_era_integrante']
                ? 'La invitación fue confirmada. Ya pertenecías a esta familia.'
                : 'Te uniste correctamente a ' . $result['familia_nombre'] . '.');
            return Response::redirect('/');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
            return Response::redirect('/invitaciones/aceptar');
        }
    }
}
