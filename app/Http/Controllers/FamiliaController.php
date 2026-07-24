<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\FamiliaService;
use App\Services\PersonaContext;
use App\Services\InvitacionService;

final class FamiliaController
{
    public function __construct(
        private readonly FamiliaService $families,
        private readonly Session $session,
        private readonly View $view,
        private readonly Validator $validator,
        private readonly PersonaContext $personContext,
        private readonly InvitacionService $invitations,
    ) {
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('familias/create', [
            'title' => 'Crear familia',
        ]));
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        if (!$this->validator->validate($data, ['nombre' => ['required', 'string', 'maxLength:150']])) {
            $this->session->flash('errors', $this->validator->errors());
            $this->session->flash('old', $data);
            return Response::redirect('/familias/create');
        }

        $familyId = $this->families->createForUser((int) $this->session->get('user_id'), (string) $data['nombre']);
        $this->session->put('family_id', $familyId);
        $this->personContext->clear();
        $this->session->flash('success', 'Familia creada correctamente.');
        return Response::redirect('/');
    }

    public function index(Request $request): Response
    {
        $userId = (int) $this->session->get('user_id');
        return Response::html($this->view->render('familias/index', [
            'title' => 'Mis familias',
            'familias' => $this->families->familiesForUser($userId),
            'familiasArchivadas' => $this->families->archivedForUser($userId),
            'familiaActivaId' => (int) $this->session->get('family_id', 0),
        ]));
    }

    public function select(Request $request, string $id): Response
    {
        $family = $this->families->findForUser((int) $id, (int) $this->session->get('user_id'));
        if ($family === null) {
            return Response::html('<h1>403</h1><p>No tienes acceso a esta familia.</p>', 403);
        }
        $this->session->put('family_id', (int) $family['id']);
        $this->personContext->clear();
        return Response::redirect('/');
    }

    public function members(Request $request): Response
    {
        $familyId = (int) $this->session->get('family_id');
        $userId = (int) $this->session->get('user_id');
        $family = $this->families->requireMembership($familyId, $userId);
        return Response::html($this->view->render('familias/members', [
            'title' => 'Integrantes',
            'familia' => $family,
            'integrantes' => $this->families->members($familyId, $userId),
            'invitaciones' => $family['rol_codigo'] === 'ADMINISTRADOR'
                ? $this->invitations->allForFamily($familyId, $userId)
                : [],
            'invitacionGenerada' => $this->view->flash('invitacion_generada'),
        ]));
    }

    public function addMember(Request $request): Response
    {
        $data = $request->all();
        if (!$this->validator->validate($data, [
            'email' => ['required', 'email', 'maxLength:254'],
            'rol' => ['required', 'in:ADMINISTRADOR,FAMILIAR'],
        ])) {
            $this->session->flash('errors', $this->validator->errors());
            return Response::redirect('/familias/integrantes');
        }

        try {
            $this->families->addMemberByEmail(
                (int) $this->session->get('family_id'),
                (int) $this->session->get('user_id'),
                (string) $data['email'],
                (string) $data['rol'],
            );
            $this->session->flash('success', 'Integrante asociado correctamente.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias/integrantes');
    }

    public function updateMemberRole(Request $request, string $id): Response
    {
        $data = $request->all();
        if (!$this->validator->validate($data, [
            'rol' => ['required', 'in:ADMINISTRADOR,FAMILIAR'],
        ])) {
            $this->session->flash('errors', $this->validator->errors());
            return Response::redirect('/familias/integrantes');
        }

        try {
            $this->families->updateMemberRole(
                (int) $this->session->get('family_id'),
                (int) $this->session->get('user_id'),
                (int) $id,
                (string) $data['rol'],
            );
            $this->session->flash('success', 'Rol actualizado correctamente.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias/integrantes');
    }

    public function archive(Request $request, string $id): Response
    {
        $familyId = (int) $id;
        $userId = (int) $this->session->get('user_id');
        try {
            $this->families->archive($familyId, $userId);
            if ((int) $this->session->get('family_id', 0) === $familyId) {
                $this->personContext->clear();
                $remaining = $this->families->familiesForUser($userId);
                if ($remaining === []) {
                    $this->session->forget('family_id');
                } else {
                    $this->session->put('family_id', (int) $remaining[0]['id']);
                }
            }
            $this->session->flash('success', 'Familia archivada. Toda su información se conserva y puede restaurarse.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias');
    }

    public function restore(Request $request, string $id): Response
    {
        try {
            $this->families->restore((int) $id, (int) $this->session->get('user_id'));
            $this->session->flash('success', 'Familia restaurada correctamente.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/familias');
    }
}
