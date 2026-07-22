<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Session;
use App\Services\FamiliaService;
use App\Services\MedicamentoService;
use App\Services\DocumentoService;
use App\Services\PersonaService;
use App\Services\AtencionService;
use App\Services\PersonaContext;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly FamiliaService $families,
        private readonly MedicamentoService $medications,
        private readonly DocumentoService $documents,
        private readonly PersonaService $people,
        private readonly AtencionService $attentions,
        private readonly PersonaContext $personContext,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = (int) $this->session->get('user_id');
        $familyId = (int) $this->session->get('family_id', 0);
        $available = $this->families->familiesForUser($userId);
        if ($familyId === 0 || $this->families->findForUser($familyId, $userId) === null) {
            if ($available === []) {
                $this->session->forget('family_id');
                $this->personContext->clear();
                return Response::redirect('/familias/create');
            }
            $familyId = (int) $available[0]['id'];
            $this->session->put('family_id', $familyId);
            $this->personContext->clear();
        }
        $family = $this->families->requireMembership($familyId, $userId);
        $people = $this->people->allForFamily($familyId);
        $selectedPersonId = (int) ($this->personContext->active()['id'] ?? 0);
        $timezone = new DateTimeZone((string) env('APP_TIMEZONE', 'America/Santiago'));
        $month = $this->requestedMonth((string) $request->input('mes', ''), $timezone);
        $monthStart = $month->modify('first day of this month');
        $monthEnd = $month->modify('last day of this month');
        $monthAttentions = [];
        if ($selectedPersonId > 0) {
            $monthAttentions = $this->attentions->allForFamily($familyId, [
                'persona_id' => $selectedPersonId,
                'desde' => $monthStart->format('Y-m-d'),
                'hasta' => $monthEnd->format('Y-m-d'),
            ]);
            usort($monthAttentions, static fn (array $left, array $right): int =>
                strcmp((string) $left['fecha_hora_local'], (string) $right['fecha_hora_local'])
            );
        }
        return Response::html($this->view->render('dashboard/index', [
            'title' => 'Panel familiar',
            'familia' => $family,
            'personas' => $people,
            'personaSeleccionada' => $selectedPersonId,
            'calendario' => $this->calendar($monthStart, $monthEnd, $monthAttentions, $timezone),
            'atencionesMes' => $monthAttentions,
            'estadosAtencion' => $this->attentions->states(),
            'mesActual' => $monthStart->format('Y-m'),
            'mesAnterior' => $monthStart->modify('-1 month')->format('Y-m'),
            'mesSiguiente' => $monthStart->modify('+1 month')->format('Y-m'),
            'tituloMes' => $this->monthTitle($monthStart),
            'medicamentosActivos' => $selectedPersonId ? $this->medications->activeForFamily($familyId, $selectedPersonId) : [],
            'documentosRecientes' => $selectedPersonId ? $this->documents->allForFamily($familyId, ['persona_id' => $selectedPersonId], 4) : [],
        ]));
    }

    private function requestedMonth(string $requested, DateTimeZone $timezone): DateTimeImmutable
    {
        $month = DateTimeImmutable::createFromFormat('!Y-m', $requested, $timezone);
        if ($month !== false && $month->format('Y-m') === $requested) {
            $year = (int) $month->format('Y');
            if ($year >= 1900 && $year <= 2100) {
                return $month;
            }
        }
        return new DateTimeImmutable('first day of this month', $timezone);
    }

    private function calendar(
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd,
        array $attentions,
        DateTimeZone $timezone,
    ): array {
        $byDate = [];
        foreach ($attentions as $attention) {
            $byDate[substr((string) $attention['fecha_hora_local'], 0, 10)][] = $attention;
        }
        $gridStart = $monthStart->modify('-' . ((int) $monthStart->format('N') - 1) . ' days');
        $gridEnd = $monthEnd->modify('+' . (7 - (int) $monthEnd->format('N')) . ' days');
        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $days = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $days[] = [
                'fecha' => $date,
                'numero' => $day->format('j'),
                'del_mes' => $day->format('Y-m') === $monthStart->format('Y-m'),
                'hoy' => $date === $today,
                'atenciones' => $byDate[$date] ?? [],
            ];
        }
        return $days;
    }

    private function monthTitle(DateTimeImmutable $month): string
    {
        $names = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $names[(int) $month->format('n')] . ' ' . $month->format('Y');
    }
}
