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
use App\Services\PersonaContext;

final class DocumentoController
{
    public function __construct(
        private readonly DocumentoService $documents,
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
        return Response::html($this->view->render('documentos/index', [
            'title' => 'Documentos', 'documentos' => $this->documents->allForFamily($this->familyId(), $filters, 0, $this->userId()),
            'personaActiva' => $person, 'tipos' => $this->documents->types(), 'filters' => $filters,
        ]));
    }

    public function create(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $old = $this->session->flashed('old', []);
        $freshForm = $old === [];
        if ($freshForm) {
            $timezone = new \DateTimeZone((string) env('APP_TIMEZONE', 'America/Santiago'));
            $old = [
                'atencion_id' => $request->input('atencion_id', ''),
                'fecha_documento' => (new \DateTimeImmutable('now', $timezone))->format('Y-m-d'),
            ];
        }
        $old['persona_id'] = (int) $person['id'];
        if (!empty($old['atencion_id'])) {
            $attention = $this->attentions->findForFamily((int) $old['atencion_id'], $this->familyId());
            if ($attention === null || (int) $attention['persona_id'] !== (int) $person['id']) { $old['atencion_id'] = ''; }
            elseif ($freshForm) { $old['fecha_documento'] = substr((string) $attention['fecha_hora_local'], 0, 10); }
        }
        return Response::html($this->form($old, $person));
    }

    public function store(Request $request): Response
    {
        $person = $this->personContext->active();
        if ($person === null) { return $this->needsPerson(); }
        $data = $request->all();
        $data['persona_id'] = (int) $person['id'];
        if (!$this->validator->validate($data, [
            'persona_id' => ['required', 'integer'], 'atencion_id' => ['integer'],
            'tipo_id' => ['required', 'integer'], 'nombre' => ['string', 'maxLength:255'], 'fecha_documento' => ['date'],
        ])) {
            $this->session->flash('errors', $this->validator->errors()); $this->session->flash('old', $data);
            return Response::redirect('/documentos/create');
        }
        try {
            $id = $this->documents->create($this->familyId(), $this->userId(), $data, $request->file('archivo'));
            $this->session->flash('success', 'Documento cargado correctamente.');
            if (!empty($data['atencion_id'])) {
                $document = $this->documents->findForFamily($id, $this->familyId());
                if ($document !== null) {
                    $this->session->flash('documento_agregado', [
                        'id' => $id,
                        'nombre' => $document['nombre'],
                        'tipo_nombre' => $document['tipo_nombre'],
                        'mime_type' => $document['mime_type'],
                        'formato' => $this->formatLabel((string) $document['mime_type']),
                    ]);
                }
                return Response::redirect('/atenciones/' . (int) $data['atencion_id']);
            }
            return Response::redirect('/documentos');
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['general' => [$exception->getMessage()]]); $this->session->flash('old', $data);
            return Response::redirect('/documentos/create');
        }
    }

    public function edit(Request $request, string $id): Response
    {
        $document = $this->documents->findForFamily((int) $id, $this->familyId());
        if ($document === null || !$this->personContext->matches((int) $document['persona_id'])) { return $this->contextMismatch(); }
        $old = $this->session->flashed('old', []);
        if ($old === [] && empty($document['fecha_documento']) && !empty($document['created_at'])) {
            $document['fecha_documento'] = substr((string) $document['created_at'], 0, 10);
        }
        $document = $old === [] ? $document : [...$document, ...$old];
        return Response::html($this->form($document, $this->personContext->active() ?? ['id' => $document['persona_id']], true));
    }

    public function update(Request $request, string $id): Response
    {
        $document = $this->documents->findForFamily((int) $id, $this->familyId());
        if ($document === null || !$this->personContext->matches((int) $document['persona_id'])) { return $this->contextMismatch(); }
        $data = $request->all();
        $data['persona_id'] = (int) $document['persona_id'];
        if (!$this->validator->validate($data, [
            'persona_id' => ['required', 'integer'], 'atencion_id' => ['integer'],
            'tipo_id' => ['required', 'integer'], 'nombre' => ['string', 'maxLength:255'], 'fecha_documento' => ['date'],
        ])) {
            $this->session->flash('errors', $this->validator->errors()); $this->session->flash('old', $data);
            return Response::redirect('/documentos/' . (int) $id . '/edit');
        }
        try {
            $this->documents->update((int) $id, $this->familyId(), $data, $request->file('archivo'));
            $this->session->flash('success', 'Documento actualizado correctamente.');
            return Response::redirect('/documentos');
        } catch (\InvalidArgumentException $exception) {
            $this->session->flash('errors', ['general' => [$exception->getMessage()]]); $this->session->flash('old', $data);
            return Response::redirect('/documentos/' . (int) $id . '/edit');
        }
    }

    public function download(Request $request, string $id): Response
    {
        $document = $this->documents->downloadForFamily((int) $id, $this->familyId());
        if ($document === null) { return Response::html('<h1>404</h1><p>Documento no encontrado.</p>', 404); }
        if (!$this->personContext->matches((int) $document['persona_id'])) { return $this->contextMismatch(); }
        return Response::download($document['absolute_path'], $document['download_name'], $document['mime_type']);
    }

    public function preview(Request $request, string $id): Response
    {
        $document = $this->documents->downloadForFamily((int) $id, $this->familyId());
        if ($document === null || !str_starts_with((string) $document['mime_type'], 'image/')) {
            return Response::html('<h1>404</h1><p>Vista previa no disponible.</p>', 404);
        }
        if (!$this->personContext->matches((int) $document['persona_id'])) {
            return $this->contextMismatch();
        }
        return Response::inlineFile(
            $document['absolute_path'],
            $document['download_name'],
            $document['mime_type'],
        );
    }

    public function destroy(Request $request, string $id): Response
    {
        $document = $this->documents->findForFamily((int) $id, $this->familyId());
        if ($document === null || !$this->personContext->matches((int) $document['persona_id'])) { return $this->contextMismatch(); }
        try {
            if (!$this->documents->delete((int) $id, $this->familyId(), $this->userId())) { return Response::html('<h1>404</h1><p>Documento no encontrado.</p>', 404); }
            $this->session->flash('success', 'Documento eliminado correctamente.');
        } catch (\Throwable $exception) {
            $this->session->flash('error', $exception->getMessage());
        }
        return Response::redirect('/documentos');
    }

    private function form(array $document, array $person, bool $editing = false): string
    {
        return $this->view->render('documentos/form', [
            'title' => $editing ? 'Editar documento' : 'Cargar documento', 'documento' => $document, 'editing' => $editing, 'errors' => $this->session->flashed('errors', []),
            'personaActiva' => $person, 'tipos' => $this->documents->types(),
            'maxUploadMb' => (int) env('MAX_UPLOAD_MB', 10),
        ]);
    }

    private function formatLabel(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'PDF',
            'image/jpeg' => 'Imagen JPG',
            'image/png' => 'Imagen PNG',
            'image/webp' => 'Imagen WEBP',
            default => 'Archivo',
        };
    }

    private function needsPerson(): Response { $this->session->flash('error', 'Selecciona una persona en la parte superior para continuar.'); return Response::redirect('/personas'); }
    private function contextMismatch(): Response { $this->session->flash('error', 'El documento no pertenece a la persona activa.'); return Response::redirect('/documentos'); }
    private function familyId(): int { return (int) $this->session->get('family_id'); }
    private function userId(): int { return (int) $this->session->get('user_id'); }
}
