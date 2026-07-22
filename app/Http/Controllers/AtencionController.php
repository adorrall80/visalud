<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AtencionService;
use App\Services\DocumentoService;
use App\Services\MedicamentoService;
use App\Services\PersonaContext;

final class AtencionController
{
    public function __construct(
        private readonly AtencionService $attentions,
        private readonly Session $session,
        private readonly View $view,
        private readonly Validator $validator,
        private readonly PersonaContext $personContext,
        private readonly MedicamentoService $medications,
        private readonly DocumentoService $documents,
    ) {
    }

    public function index(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $filters = $request->all();
        $filters['persona_id'] = (int) $person['id'];
        return Response::html($this->view->render('atenciones/index', [
            'title' => 'Atenciones',
            'atenciones' => $this->attentions->allForFamily($this->familyId(), $filters),
            'personaActiva' => $person,
            'tipos' => $this->attentions->types(),
            'estados' => $this->attentions->states(),
            'filters' => $filters,
        ]));
    }

    public function create(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $old = $this->session->flashed('old', []);
        $requestedDate = (string) $request->input('fecha', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate);
        if ($old === [] && $date !== false && $date->format('Y-m-d') === $requestedDate) {
            $old['fecha_hora'] = $requestedDate . 'T09:00';
        }
        $old['persona_id'] = (int) $person['id'];
        return Response::html($this->form($old, '/atenciones', 'POST', 'Registrar atención', $person));
    }

    public function store(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $data = $request->all();
        $data['persona_id'] = (int) $person['id'];
        if (!$this->valid($data)) { return $this->invalid('/atenciones/create', $data); }
        try {
            $id = $this->attentions->create($this->familyId(), $this->userId(), $data);
            $this->session->flash('success', 'Atención registrada correctamente.');
            return Response::redirect('/atenciones/' . $id);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['general' => [$exception->getMessage()]]);
            $this->session->flash('old', $data);
            return Response::redirect('/atenciones/create');
        }
    }

    public function show(Request $request, string $id): Response
    {
        $attention = $this->attentions->findForFamily((int) $id, $this->familyId());
        if ($attention === null) { return Response::html('<h1>404</h1><p>Atención no encontrada.</p>', 404); }
        if (!$this->personContext->matches((int) $attention['persona_id'])) { return $this->contextMismatch(); }
        return Response::html($this->view->render('atenciones/show', [
            'title' => $attention['tipo_nombre'],
            'atencion' => $attention,
            'medicamentos' => $this->medications->allForFamily($this->familyId(), [
                'persona_id' => (int) $attention['persona_id'],
                'atencion_id' => (int) $attention['id'],
            ]),
            'documentos' => $this->documents->allForFamily($this->familyId(), [
                'persona_id' => (int) $attention['persona_id'],
                'atencion_id' => (int) $attention['id'],
            ]),
        ]));
    }

    public function edit(Request $request, string $id): Response
    {
        $attention = $this->attentions->findForFamily((int) $id, $this->familyId());
        if ($attention === null) { return Response::html('<h1>404</h1><p>Atención no encontrada.</p>', 404); }
        if (!$this->personContext->matches((int) $attention['persona_id'])) { return $this->contextMismatch(); }
        $attention['fecha_hora'] = $attention['fecha_hora_local'];
        return Response::html($this->form(
            $this->session->flashed('old', $attention),
            '/atenciones/' . $attention['id'],
            'PUT',
            'Editar atención',
            $this->personContext->requireActive(),
        ));
    }

    public function update(Request $request, string $id): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $current = $this->attentions->findForFamily((int) $id, $this->familyId());
        if ($current === null || (int) $current['persona_id'] !== (int) $person['id']) { return $this->contextMismatch(); }
        $data = $request->all();
        $data['persona_id'] = (int) $person['id'];
        if (!$this->valid($data)) { return $this->invalid('/atenciones/' . (int) $id . '/edit', $data); }
        try {
            $this->attentions->update((int) $id, $this->familyId(), $this->userId(), $data);
            $this->session->flash('success', 'Atención actualizada correctamente.');
            return Response::redirect('/atenciones/' . (int) $id);
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['general' => [$exception->getMessage()]]);
            $this->session->flash('old', $data);
            return Response::redirect('/atenciones/' . (int) $id . '/edit');
        }
    }

    private function form(array $attention, string $action, string $method, string $title, array $person): string
    {
        return $this->view->render('atenciones/form', [
            'title' => $title, 'atencion' => $attention, 'errors' => $this->session->flashed('errors', []),
            'action' => $action, 'method' => $method, 'personaActiva' => $person,
            'tipos' => $this->attentions->types(), 'estados' => $this->attentions->states(),
        ]);
    }

    private function valid(array $data): bool
    {
        return $this->validator->validate($data, [
            'persona_id' => ['required', 'integer'], 'tipo_id' => ['required', 'integer'],
            'estado_id' => ['required', 'integer'], 'fecha_hora' => ['required', 'datetime'],
            'profesional' => ['string', 'maxLength:150'], 'especialidad' => ['string', 'maxLength:120'],
            'centro_medico' => ['string', 'maxLength:150'], 'proxima_fecha' => ['date'],
        ]);
    }

    private function invalid(string $location, array $data): Response { $this->session->flash('errors', $this->validator->errors()); $this->session->flash('old', $data); return Response::redirect($location); }
    private function needsPerson(): Response { $this->session->flash('error', 'Selecciona una persona en la parte superior para continuar.'); return Response::redirect('/personas'); }
    private function contextMismatch(): Response { $this->session->flash('error', 'La atención no pertenece a la persona activa.'); return Response::redirect('/atenciones'); }
    private function familyId(): int { return (int) $this->session->get('family_id'); }
    private function userId(): int { return (int) $this->session->get('user_id'); }
}
