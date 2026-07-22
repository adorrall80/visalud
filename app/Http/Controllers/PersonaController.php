<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\PersonaService;
use App\Services\MedicamentoService;
use App\Services\PersonaContext;

final class PersonaController
{
    public function __construct(
        private readonly PersonaService $people,
        private readonly Session $session,
        private readonly View $view,
        private readonly Validator $validator,
        private readonly MedicamentoService $medications,
        private readonly PersonaContext $personContext,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('personas/index', [
            'title' => 'Personas',
            'personas' => $this->people->allForFamily($this->familyId()),
        ]));
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('personas/form', [
            'title' => 'Agregar persona',
            'persona' => $this->session->flashed('old', []),
            'errors' => $this->session->flashed('errors', []),
            'action' => '/personas',
            'method' => 'POST',
        ]));
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        if (!$this->valid($data)) {
            return $this->invalid('/personas/create', $data);
        }
        try {
            $id = $this->people->create($this->familyId(), $data);
            $this->personContext->select($id);
            $this->session->flash('success', 'Persona agregada correctamente.');
            return Response::redirect('/personas/' . $id);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['identificacion' => [$exception->getMessage()]]);
            $this->session->flash('old', $data);
            return Response::redirect('/personas/create');
        }
    }

    public function show(Request $request, string $id): Response
    {
        $person = $this->people->findForFamily((int) $id, $this->familyId());
        if ($person === null) {
            return Response::html('<h1>404</h1><p>Persona no encontrada.</p>', 404);
        }
        $this->personContext->select((int) $person['id']);
        return Response::html($this->view->render('personas/show', [
            'title' => $person['nombre'],
            'persona' => $person,
            'medicamentosActivos' => $this->medications->activeForFamily($this->familyId(), (int) $person['id']),
        ]));
    }

    public function edit(Request $request, string $id): Response
    {
        $person = $this->people->findForFamily((int) $id, $this->familyId());
        if ($person === null) {
            return Response::html('<h1>404</h1><p>Persona no encontrada.</p>', 404);
        }
        return Response::html($this->view->render('personas/form', [
            'title' => 'Editar persona',
            'persona' => $this->session->flashed('old', $person),
            'errors' => $this->session->flashed('errors', []),
            'action' => '/personas/' . $person['id'],
            'method' => 'PUT',
        ]));
    }

    public function update(Request $request, string $id): Response
    {
        $data = $request->all();
        if (!$this->valid($data)) {
            return $this->invalid('/personas/' . (int) $id . '/edit', $data);
        }
        try {
            $this->people->update((int) $id, $this->familyId(), $data);
            $this->session->flash('success', 'Ficha actualizada correctamente.');
            return Response::redirect('/personas/' . (int) $id);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['identificacion' => [$exception->getMessage()]]);
            $this->session->flash('old', $data);
            return Response::redirect('/personas/' . (int) $id . '/edit');
        }
    }

    private function valid(array $data): bool
    {
        $rules = [
            'nombre' => ['required', 'string', 'maxLength:150'],
            'identificacion' => ['string', 'maxLength:30'],
            'fecha_nacimiento' => ['date', 'notFuture'],
            'grupo_sanguineo' => ['string', 'maxLength:10'],
            'contacto_emergencia' => ['string', 'maxLength:150'],
            'telefono_emergencia' => ['string', 'maxLength:30'],
        ];
        return $this->validator->validate($data, $rules);
    }

    private function invalid(string $location, array $data): Response
    {
        $this->session->flash('errors', $this->validator->errors());
        $this->session->flash('old', $data);
        return Response::redirect($location);
    }

    private function familyId(): int
    {
        return (int) $this->session->get('family_id');
    }
}
