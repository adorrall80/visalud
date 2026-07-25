<?php

declare(strict_types=1);

namespace App\Services;

final class AtencionFichaAiPromptService
{
    public function render(array $attention, array $medications, array $documents): string
    {
        $lines = [
            'Actúa como asistente de salud familiar. Con la siguiente información de una atención, genera un resumen claro, ordenado y fácil de entender para la familia.',
            '',
            'Incluye:',
            '- Motivo principal de la atención.',
            '- Hallazgos, diagnóstico o resultado.',
            '- Indicaciones y próximos pasos.',
            '- Medicamentos asociados, dosis, frecuencia y horarios si existen.',
            '- Documentos adjuntos: solo menciona qué documentos hay y los datos registrados.',
            '- Señales de alerta o temas que conviene consultar con un profesional, sin inventar información.',
            '',
            'No reemplaces la evaluación médica. Si falta información, indícalo explícitamente.',
            '',
            'INFORMACIÓN DE LA ATENCIÓN',
            'Persona: ' . $this->value($attention['persona_nombre'] ?? null),
            'Fecha y hora: ' . $this->value($attention['fecha_hora_formato'] ?? null),
            'Tipo: ' . $this->value($attention['tipo_nombre'] ?? null),
            'Estado: ' . $this->value($attention['estado_nombre'] ?? null),
            'Profesional: ' . $this->value($attention['profesional'] ?? null),
            'Especialidad: ' . $this->value($attention['especialidad'] ?? null),
            'Centro o lugar: ' . $this->value($attention['centro_medico'] ?? null),
            'Próximo control o sesión: ' . $this->value($attention['proxima_fecha'] ?? null),
            'Registrado por: ' . $this->value($attention['registrado_por_nombre'] ?? null),
            '',
            'DETALLE CLÍNICO',
            'Motivo: ' . $this->value($attention['motivo'] ?? null),
            'Diagnóstico o resultado: ' . $this->value($attention['diagnostico_resultado'] ?? null),
            'Indicaciones: ' . $this->value($attention['indicaciones'] ?? null),
        ];

        if (!empty($attention['temas_abordados'])) {
            $lines[] = 'Temas abordados: ' . $this->value($attention['temas_abordados']);
        }
        if (!empty($attention['acuerdos'])) {
            $lines[] = 'Acuerdos y tareas: ' . $this->value($attention['acuerdos']);
        }

        $lines[] = '';
        $lines[] = 'MEDICAMENTOS ASOCIADOS';
        if ($medications === []) {
            $lines[] = 'No hay medicamentos asociados a esta atención.';
        } else {
            foreach ($medications as $index => $medication) {
                $lines[] = ($index + 1) . '. ' . $this->value($medication['nombre'] ?? null);
                $lines[] = '   Dosis: ' . $this->value($medication['dosis'] ?? null);
                $lines[] = '   Frecuencia: ' . $this->value($medication['frecuencia'] ?? null);
                $lines[] = '   Horarios: ' . $this->value($medication['horarios_texto'] ?? null);
                $lines[] = '   Estado: ' . $this->value($medication['estado_nombre'] ?? null);
            }
        }

        $lines[] = '';
        $lines[] = 'DOCUMENTOS ASOCIADOS';
        if ($documents === []) {
            $lines[] = 'No hay documentos asociados a esta atención.';
        } else {
            $lines[] = 'Doc adjuntos:';
            foreach ($documents as $index => $document) {
                $date = $document['fecha_documento'] ?: substr((string) ($document['created_at'] ?? ''), 0, 10);
                $lines[] = ($index + 1) . '. ' . $this->value($document['nombre'] ?? null);
                $lines[] = '   Tipo: ' . $this->value($document['tipo_nombre'] ?? null);
                $lines[] = '   Fecha: ' . $this->value($date);
                $lines[] = '   Formato: ' . $this->value($document['mime_type'] ?? null);
                $lines[] = '   Subido por: ' . $this->value($document['subido_por_nombre'] ?? null);
                $lines[] = '   Descripción: ' . $this->value($document['descripcion'] ?? null);
            }
        }

        return implode("\n", $lines);
    }

    private function value(mixed $value): string
    {
        $text = trim((string) $value);
        return $text === '' ? 'No informado' : preg_replace('/\s+/', ' ', $text);
    }
}