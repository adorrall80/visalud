<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;

final class AtencionFichaPdfService
{
    private const MAX_MERGED_PDF_BYTES = 25 * 1024 * 1024;

    public function render(array $attention, array $medications, array $documents, string $finalText = ''): string
    {
        $temporaryFiles = [];
        $sources = [];

        try {
            $basePdf = $this->renderHtml($this->html($attention, $medications));
            $sources[] = $this->temporaryPdf($basePdf, $temporaryFiles);

            if ($documents === []) {
                $sources[] = $this->temporaryPdf($this->renderHtml($this->emptyDocumentsHtml()), $temporaryFiles);
            }

            foreach ($documents as $document) {
                $sources[] = $this->temporaryPdf($this->renderHtml($this->documentHtml($document)), $temporaryFiles);
                $pdfPath = $this->mergeablePdfPath($document);
                if ($pdfPath !== null) {
                    $sources[] = $pdfPath;
                }
            }

            if (trim($finalText) !== '') {
                $sources[] = $this->temporaryPdf($this->renderHtml($this->finalTextHtml($finalText)), $temporaryFiles);
            }

            return $this->mergePdfSources($sources, $basePdf);
        } finally {
            foreach ($temporaryFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    public function filename(array $attention): string
    {
        $person = $this->slug((string) ($attention['persona_nombre'] ?? 'persona'));
        $dateTime = (string) ($attention['fecha_hora_local'] ?? date('Y-m-d\TH:i'));
        $date = substr($dateTime, 0, 10);
        $time = str_replace(':', '', substr($dateTime, 11, 5) ?: '0000');
        $id = max(0, (int) ($attention['id'] ?? 0));
        return 'ficha-atencion-' . $person . '-' . $date . '-' . $time . '-id' . $id . '.pdf';
    }

    private function html(array $attention, array $medications): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>' . $this->css() . '</style></head><body>'
            . '<header><p class="eyebrow">Visalud - ficha de atención</p><h1>' . $this->escape($attention['tipo_nombre'] ?? 'Atención') . '</h1><p class="subtitle">' . $this->escape($attention['persona_nombre'] ?? '') . ' · ' . $this->escape($attention['fecha_hora_formato'] ?? '') . '</p></header>'
            . $this->summary($attention)
            . $this->clinicalDetails($attention)
            . $this->medications($medications)
            . '<footer>Ficha generada desde Visalud. Documento privado de uso familiar.</footer>'
            . '</body></html>';
    }

    private function renderHtml(string $html): string
    {
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        return $pdf->output();
    }

    private function summary(array $attention): string
    {
        $items = [
            'Persona' => $attention['persona_nombre'] ?? '',
            'Fecha y hora' => $attention['fecha_hora_formato'] ?? '',
            'Tipo' => $attention['tipo_nombre'] ?? '',
            'Estado' => $attention['estado_nombre'] ?? '',
            'Profesional' => $attention['profesional'] ?: 'No informado',
            'Especialidad' => $attention['especialidad'] ?: 'No informada',
            'Centro o lugar' => $attention['centro_medico'] ?: 'No informado',
            'Próximo control' => $attention['proxima_fecha'] ?: 'No informado',
            'Registrado por' => $attention['registrado_por_nombre'] ?? '',
        ];

        $html = '<section class="card"><h2>Resumen</h2><table>';
        foreach ($items as $label => $value) {
            $html .= '<tr><th>' . $this->escape($label) . '</th><td>' . $this->escape($value) . '</td></tr>';
        }
        return $html . '</table></section>';
    }

    private function clinicalDetails(array $attention): string
    {
        $items = [
            'Motivo' => $attention['motivo'] ?: 'Sin información',
            'Diagnóstico o resultado' => $attention['diagnostico_resultado'] ?: 'Sin información',
            'Indicaciones' => $attention['indicaciones'] ?: 'Sin información',
            'Temas abordados' => $attention['temas_abordados'] ?: '',
            'Acuerdos y tareas' => $attention['acuerdos'] ?: '',
        ];

        $html = '<section class="card"><h2>Detalle clínico</h2>';
        foreach ($items as $label => $value) {
            if ($value === '') {
                continue;
            }
            $html .= '<div class="block"><h3>' . $this->escape($label) . '</h3><p>' . nl2br($this->escape($value)) . '</p></div>';
        }
        return $html . '</section>';
    }

    private function medications(array $medications): string
    {
        $html = '<section class="card"><h2>Medicamentos asociados</h2>';
        if ($medications === []) {
            return $html . '<p class="muted">No hay medicamentos asociados a esta atención.</p></section>';
        }

        $html .= '<table><thead><tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Estado</th></tr></thead><tbody>';
        foreach ($medications as $medication) {
            $detail = trim((string) ($medication['horarios_texto'] ?? ''));
            $html .= '<tr><td>' . $this->escape($medication['nombre'] ?? '') . ($detail !== '' ? '<br><small>Horarios: ' . $this->escape($detail) . '</small>' : '') . '</td><td>' . $this->escape($medication['dosis'] ?? '') . '</td><td>' . $this->escape($medication['frecuencia'] ?: 'No informada') . '</td><td>' . $this->escape($medication['estado_nombre'] ?? '') . '</td></tr>';
        }
        return $html . '</tbody></table></section>';
    }

    private function emptyDocumentsHtml(): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>' . $this->css() . '</style></head><body>'
            . '<header><p class="eyebrow">Documentos asociados</p><h1>Sin documentos</h1></header>'
            . '<section class="card"><p class="muted">No hay documentos asociados a esta atención.</p></section>'
            . '</body></html>';
    }

    private function documentHtml(array $document): string
    {
        $date = $document['fecha_documento'] ?: substr((string) ($document['created_at'] ?? ''), 0, 10);
        $image = $this->imageDataUri($document);
        $isPdf = ($document['mime_type'] ?? '') === 'application/pdf';
        $html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>' . $this->css() . '</style></head><body>'
            . '<header><p class="eyebrow">Documento asociado</p><h1>' . $this->escape($document['nombre'] ?? 'Documento') . '</h1></header>'
            . '<section class="card"><h2>Datos del archivo</h2><table>'
            . '<tr><th>Tipo</th><td>' . $this->escape($document['tipo_nombre'] ?? 'Documento') . '</td></tr>'
            . '<tr><th>Fecha</th><td>' . $this->escape($date) . '</td></tr>'
            . '<tr><th>Subido por</th><td>' . $this->escape($document['subido_por_nombre'] ?? 'No informado') . '</td></tr>'
            . '<tr><th>Formato</th><td>' . $this->escape($document['mime_type'] ?? 'Archivo') . '</td></tr>'
            . '</table>';

        if (!empty($document['descripcion'])) {
            $html .= '<div class="block"><h3>Descripción</h3><p>' . nl2br($this->escape($document['descripcion'])) . '</p></div>';
        }

        if ($image !== null) {
            $html .= '<div class="block"><h3>Contenido del archivo</h3><img class="document-image" src="' . $image . '" alt=""></div>';
        } elseif ($isPdf && $this->mergeablePdfPath($document) !== null) {
            $html .= '<p class="muted">El contenido del PDF se incluye a continuación.</p>';
        } elseif ($isPdf) {
            $html .= '<p class="muted">PDF asociado disponible en el sistema, pero no fue posible incorporarlo a esta descarga.</p>';
        } else {
            $html .= '<p class="muted">Archivo asociado disponible en el sistema.</p>';
        }

        return $html . '</section></body></html>';
    }

    private function finalTextHtml(string $text): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>' . $this->css() . '</style></head><body>'
            . '<header><p class="eyebrow">Ficha de atención</p><h1>Info adicional</h1></header>'
            . '<section class="card final-text-card"><div class="block"><p>' . $this->escape($text) . '</p></div></section>'
            . '</body></html>';
    }

    private function imageDataUri(array $document): ?string
    {
        $mimeType = (string) ($document['mime_type'] ?? '');
        $path = (string) ($document['absolute_path'] ?? '');
        if (!str_starts_with($mimeType, 'image/') || $path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    private function mergePdfSources(array $sources, string $fallbackPdf): string
    {
        $totalBytes = 0;
        foreach ($sources as $path) {
            $size = filesize($path);
            $totalBytes += is_int($size) ? $size : 0;
        }
        if ($totalBytes > self::MAX_MERGED_PDF_BYTES) {
            $this->logMergeWarning('Se omitió generar el PDF combinado porque supera el límite configurado.');
            return $fallbackPdf;
        }

        try {
            $merged = new Fpdi();
            foreach ($sources as $path) {
                try {
                    $this->appendPdf($merged, $path);
                } catch (\Throwable $exception) {
                    $this->logMergeWarning('No fue posible fusionar una parte del PDF: ' . $exception->getMessage());
                }
            }
            return $merged->Output('S');
        } catch (\Throwable $exception) {
            $this->logMergeWarning('No fue posible generar el PDF combinado: ' . $exception->getMessage());
            return $fallbackPdf;
        }
    }

    private function mergeablePdfPath(array $document): ?string
    {
        $path = (string) ($document['absolute_path'] ?? '');
        if (($document['mime_type'] ?? '') !== 'application/pdf' || $path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }
        return $path;
    }

    private function temporaryPdf(string $content, array &$temporaryFiles): string
    {
        $path = tempnam(sys_get_temp_dir(), 'visalud-ficha-');
        if (!is_string($path) || file_put_contents($path, $content) === false) {
            throw new \RuntimeException('No fue posible preparar una parte temporal de la ficha PDF.');
        }
        $temporaryFiles[] = $path;
        return $path;
    }

    private function appendPdf(Fpdi $merged, string $path): void
    {
        $pageCount = $merged->setSourceFile($path);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $merged->importPage($pageNumber);
            $size = $merged->getTemplateSize($template);
            $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merged->useTemplate($template);
        }
    }

    private function logMergeWarning(string $message): void
    {
        error_log('[Visalud ficha PDF] ' . $message);
    }

    private function css(): string
    {
        return 'body{font-family:DejaVu Sans,sans-serif;color:#173d35;font-size:12px;line-height:1.45;margin:28px}header{border-bottom:3px solid #2d7f6f;padding-bottom:14px;margin-bottom:18px}.eyebrow{color:#2d7f6f;font-size:11px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;margin:0 0 4px}h1{font-size:27px;margin:0;color:#12362f}h2{font-size:16px;margin:0 0 12px;color:#12362f}h3{font-size:12px;margin:0 0 4px;color:#12362f}.subtitle{font-size:13px;margin:4px 0 0;color:#5b716b}.card{border:1px solid #d7e4df;border-radius:10px;padding:14px;margin-bottom:14px;page-break-inside:avoid}.final-text-card{page-break-inside:auto}.block{margin-bottom:10px}.block p{margin:0;white-space:normal}.final-text-card .block p{white-space:pre-wrap;overflow-wrap:break-word}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #e3ece8;padding:7px 8px;text-align:left;vertical-align:top}th{width:32%;color:#5b716b;font-weight:bold}.muted{color:#6b7f79}.document{border-top:1px solid #e3ece8;padding-top:10px;margin-top:10px;page-break-inside:avoid}.document:first-of-type{border-top:0;margin-top:0;padding-top:0}.document p{margin:0 0 7px}.document-image{display:block;max-width:100%;max-height:520px;margin-top:8px;border:1px solid #d7e4df;border-radius:8px}small{color:#5b716b}footer{border-top:1px solid #d7e4df;color:#6b7f79;font-size:10px;margin-top:18px;padding-top:10px}';
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = is_string($ascii) ? strtolower($ascii) : strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?: 'persona';
        return trim($slug, '-') ?: 'persona';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}