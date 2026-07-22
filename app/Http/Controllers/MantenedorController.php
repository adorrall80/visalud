<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\MantenedorService;

final class MantenedorController
{
    public function __construct(
        private readonly MantenedorService $catalogs,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $processes = $this->catalogs->processes();
        $processId = (int) $request->input('proceso_id', $processes[0]['id'] ?? 0);
        $process = $this->catalogs->process($processId);
        if ($process === null && $processes !== []) {
            $process = $processes[0];
            $processId = (int) $process['id'];
        }
        return Response::html($this->view->render('mantenedores/index', [
            'title' => 'Mantenedores',
            'procesos' => $processes,
            'proceso' => $process,
            'estados' => $process ? $this->catalogs->values('estados', $processId) : [],
            'tipos' => $process ? $this->catalogs->values('tipos', $processId) : [],
        ]));
    }

    public function create(Request $request, string $kind): Response
    {
        $processId = (int) $request->input('proceso_id');
        $value = $this->session->flashed('old', [
            'proceso_id' => $processId,
            'activo' => 1,
            'orden' => 0,
            'color' => '#64748B',
        ]);
        return Response::html($this->form($kind, $value, '/mantenedores/' . $kind, 'POST', 'Agregar valor'));
    }

    public function store(Request $request, string $kind): Response
    {
        return $this->save($kind, null, $request->all());
    }

    public function edit(Request $request, string $kind, string $id): Response
    {
        $value = $this->catalogs->find($kind, (int) $id);
        if ($value === null) {
            return Response::html('<h1>404</h1><p>Valor no encontrado.</p>', 404);
        }
        return Response::html($this->form(
            $kind,
            $this->session->flashed('old', $value),
            '/mantenedores/' . $kind . '/' . $value['id'],
            'PUT',
            'Editar valor',
        ));
    }

    public function update(Request $request, string $kind, string $id): Response
    {
        return $this->save($kind, (int) $id, $request->all());
    }

    public function toggle(Request $request, string $kind, string $id): Response
    {
        try {
            $value = $this->catalogs->find($kind, (int) $id);
            if ($value === null) {
                throw new \InvalidArgumentException('Valor no encontrado.');
            }
            $active = $this->catalogs->toggle($kind, (int) $id);
            $this->session->flash('success', $active ? 'Valor activado correctamente.' : 'Valor desactivado correctamente.');
            return Response::redirect('/mantenedores?proceso_id=' . $value['proceso_id']);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('error', $exception->getMessage());
            return Response::redirect('/mantenedores');
        }
    }

    public function destroy(Request $request, string $kind, string $id): Response
    {
        $value = $this->catalogs->find($kind, (int) $id);
        try {
            if ($value === null) {
                throw new \InvalidArgumentException('Valor no encontrado.');
            }
            $this->catalogs->delete($kind, (int) $id);
            $this->session->flash('success', 'Valor eliminado correctamente.');
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/mantenedores' . ($value ? '?proceso_id=' . $value['proceso_id'] : ''));
    }

    private function save(string $kind, ?int $id, array $data): Response
    {
        try {
            if ($id === null) {
                $this->catalogs->create($kind, $data);
                $message = 'Valor creado correctamente.';
            } else {
                $this->catalogs->update($kind, $id, $data);
                $message = 'Valor actualizado correctamente.';
            }
            $this->session->flash('success', $message);
            return Response::redirect('/mantenedores?proceso_id=' . (int) $data['proceso_id']);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['general' => [$exception->getMessage()]]);
            $this->session->flash('old', $data);
            $location = $id === null
                ? '/mantenedores/' . $kind . '/create?proceso_id=' . (int) ($data['proceso_id'] ?? 0)
                : '/mantenedores/' . $kind . '/' . $id . '/edit';
            return Response::redirect($location);
        }
    }

    private function form(string $kind, array $value, string $action, string $method, string $title): string
    {
        return $this->view->render('mantenedores/form', [
            'title' => $title,
            'kind' => $kind,
            'valor' => $value,
            'procesos' => $this->catalogs->processes(),
            'errors' => $this->session->flashed('errors', []),
            'action' => $action,
            'method' => $method,
        ]);
    }
}
