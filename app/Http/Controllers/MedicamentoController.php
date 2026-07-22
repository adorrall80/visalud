<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AtencionService;
use App\Services\MedicamentoService;
use App\Services\PersonaContext;

final class MedicamentoController
{
    public function __construct(
        private readonly MedicamentoService $medications,
        private readonly AtencionService $attentions,
        private readonly Session $session,
        private readonly View $view,
        private readonly Validator $validator,
        private readonly PersonaContext $personContext,
    ) {
    }

    public function index(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $filters = $request->all();
        $filters['persona_id'] = (int) $person['id'];
        return Response::html($this->view->render('medicamentos/index', [
            'title' => 'Medicamentos', 'medicamentos' => $this->medications->allForFamily($this->familyId(), $filters),
            'personaActiva' => $person, 'estados' => $this->medications->states(), 'filters' => $filters,
        ]));
    }

    public function create(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $old = $this->session->flashed('old', []);
        if ($old === []) { $old['atencion_id'] = $request->input('atencion_id', ''); }
        $old['persona_id'] = (int) $person['id'];
        if (!empty($old['atencion_id'])) {
            $attention = $this->attentions->findForFamily((int) $old['atencion_id'], $this->familyId());
            if ($attention === null || (int) $attention['persona_id'] !== (int) $person['id']) { $old['atencion_id'] = ''; }
        }
        return Response::html($this->form($old, '/medicamentos', 'POST', 'Registrar medicamento', $person));
    }

    public function store(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $data = $request->all();
        $data['persona_id'] = (int) $person['id'];
        if (!$this->valid($data)) { return $this->invalid('/medicamentos/create', $data); }
        try {
            $id = $this->medications->create($this->familyId(), $data);
            $this->session->flash('success', 'Medicamento registrado correctamente.');
            if (!empty($data['atencion_id'])) {
                $this->session->flash('medicamento_agregado', [
                    'id' => $id,
                    'nombre' => trim((string) $data['nombre']),
                    'dosis' => trim((string) $data['dosis']),
                ]);
                return Response::redirect('/atenciones/' . (int) $data['atencion_id']);
            }
            return Response::redirect('/medicamentos/' . $id);
        } catch (\InvalidArgumentException $exception) { return $this->domainError('/medicamentos/create', $data, $exception); }
    }

    public function show(Request $request, string $id): Response
    {
        $medication = $this->medications->findForFamily((int) $id, $this->familyId());
        if ($medication === null) { return Response::html('<h1>404</h1><p>Medicamento no encontrado.</p>', 404); }
        if (!$this->personContext->matches((int) $medication['persona_id'])) { return $this->contextMismatch(); }
        return Response::html($this->view->render('medicamentos/show', ['title' => $medication['nombre'], 'medicamento' => $medication]));
    }

    public function edit(Request $request, string $id): Response
    {
        $medication = $this->medications->findForFamily((int) $id, $this->familyId());
        if ($medication === null) { return Response::html('<h1>404</h1><p>Medicamento no encontrado.</p>', 404); }
        if (!$this->personContext->matches((int) $medication['persona_id'])) { return $this->contextMismatch(); }
        return Response::html($this->form(
            $this->session->flashed('old', $medication), '/medicamentos/' . $medication['id'], 'PUT',
            'Editar medicamento', $this->personContext->requireActive(),
        ));
    }

    public function update(Request $request, string $id): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $current = $this->medications->findForFamily((int) $id, $this->familyId());
        if ($current === null || (int) $current['persona_id'] !== (int) $person['id']) { return $this->contextMismatch(); }
        $data = $request->all();
        $data['persona_id'] = (int) $person['id'];
        if (!$this->valid($data)) { return $this->invalid('/medicamentos/' . (int) $id . '/edit', $data); }
        try {
            $this->medications->update((int) $id, $this->familyId(), $data);
            $this->session->flash('success', 'Medicamento actualizado correctamente.');
            return Response::redirect('/medicamentos/' . (int) $id);
        } catch (\InvalidArgumentException $exception) { return $this->domainError('/medicamentos/' . (int) $id . '/edit', $data, $exception); }
    }

    private function form(array $medication, string $action, string $method, string $title, array $person): string
    {
        return $this->view->render('medicamentos/form', [
            'title' => $title, 'medicamento' => $medication, 'errors' => $this->session->flashed('errors', []),
            'action' => $action, 'method' => $method, 'personaActiva' => $person,
            'estados' => $this->medications->states(),
            'atenciones' => $this->attentions->allForFamily($this->familyId(), ['persona_id' => $person['id']]),
        ]);
    }

    private function valid(array $data): bool
    {
        return $this->validator->validate($data, [
            'persona_id' => ['required', 'integer'], 'atencion_id' => ['integer'],
            'estado_id' => ['required', 'integer'], 'nombre' => ['required', 'string', 'maxLength:150'],
            'dosis' => ['required', 'string', 'maxLength:100'], 'frecuencia' => ['string', 'maxLength:150'],
            'fecha_inicio' => ['required', 'date'], 'fecha_termino' => ['date'],
        ]);
    }

    private function invalid(string $location, array $data): Response { $this->session->flash('errors', $this->validator->errors()); $this->session->flash('old', $data); return Response::redirect($location); }
    private function domainError(string $location, array $data, \InvalidArgumentException $exception): Response { $this->session->flash('errors', ['general' => [$exception->getMessage()]]); $this->session->flash('old', $data); return Response::redirect($location); }
    private function needsPerson(): Response { $this->session->flash('error', 'Selecciona una persona en la parte superior para continuar.'); return Response::redirect('/personas'); }
    private function contextMismatch(): Response { $this->session->flash('error', 'El medicamento no pertenece a la persona activa.'); return Response::redirect('/medicamentos'); }
    private function familyId(): int { return (int) $this->session->get('family_id'); }
}
