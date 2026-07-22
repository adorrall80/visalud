<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;

final class DemoSeeder
{
    private PDO $database;

    public function run(PDO $database): void
    {
        $this->database = $database;
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $userId = $this->demoUser();
            $familyId = $this->demoFamily();
            $this->associateUsers($familyId, $userId);

            $elenaId = $this->person($familyId, [
                'nombre' => 'Elena Demo',
                'identificacion' => 'DEMO-ELENA',
                'fecha_nacimiento' => '1958-04-12',
                'grupo_sanguineo' => 'O+',
                'alergias' => 'Penicilina',
                'enfermedades_cronicas' => 'Hipertensión arterial',
                'contacto_emergencia' => 'Carlos Demo',
                'telefono_emergencia' => '+56 9 1111 1111',
                'observaciones' => 'Datos ficticios creados exclusivamente para revisar el sistema.',
            ]);
            $mateoId = $this->person($familyId, [
                'nombre' => 'Mateo Demo',
                'identificacion' => 'DEMO-MATEO',
                'fecha_nacimiento' => '2015-09-03',
                'grupo_sanguineo' => 'A+',
                'alergias' => 'Sin alergias conocidas',
                'enfermedades_cronicas' => null,
                'contacto_emergencia' => 'Andrea Demo',
                'telefono_emergencia' => '+56 9 2222 2222',
                'observaciones' => 'Perfil infantil ficticio para pruebas de navegación.',
            ]);

            $consultationId = $this->attention($elenaId, $userId, [
                'marker' => 'CONSULTA_CONTROL',
                'tipo' => 'CONSULTA_MEDICA',
                'estado' => 'REALIZADA',
                'fecha_hora' => $this->utcDateTime('-14 days', '10:00:00'),
                'profesional' => 'Dra. Carolina Ejemplo',
                'especialidad' => 'Medicina general',
                'centro_medico' => 'Centro Médico Demo',
                'motivo' => 'Control de presión arterial',
                'diagnostico_resultado' => 'Presión estable dentro del rango esperado.',
                'indicaciones' => 'Mantener tratamiento y actividad física moderada.',
                'proxima_fecha' => date('Y-m-d', strtotime('+30 days')),
            ]);
            $this->attention($mateoId, $userId, [
                'marker' => 'TERAPIA_APOYO',
                'tipo' => 'TERAPIA',
                'estado' => 'REALIZADA',
                'fecha_hora' => $this->utcDateTime('-7 days', '16:30:00'),
                'profesional' => 'Ps. Daniela Ejemplo',
                'especialidad' => 'Psicología infantil',
                'centro_medico' => 'Consulta Demo',
                'motivo' => 'Sesión de acompañamiento emocional',
                'diagnostico_resultado' => 'Buena participación durante la sesión.',
                'indicaciones' => 'Continuar rutina y registro de emociones.',
                'temas_abordados' => 'Reconocimiento de emociones y comunicación familiar.',
                'acuerdos' => 'Practicar una pausa consciente al finalizar el día.',
                'proxima_fecha' => date('Y-m-d', strtotime('+7 days')),
            ]);
            $this->attention($elenaId, $userId, [
                'marker' => 'EXAMEN_LABORATORIO',
                'tipo' => 'EXAMEN',
                'estado' => 'REALIZADA',
                'fecha_hora' => $this->utcDateTime('-3 days', '08:30:00'),
                'profesional' => 'Laboratorio Demo',
                'especialidad' => 'Laboratorio clínico',
                'centro_medico' => 'Centro de Diagnóstico Demo',
                'motivo' => 'Perfil bioquímico de control',
                'diagnostico_resultado' => 'Resultados ficticios sin alteraciones relevantes.',
                'indicaciones' => 'Revisar resultados en próximo control.',
            ]);
            $this->attention($elenaId, $userId, [
                'marker' => 'CONSULTA_PROGRAMADA',
                'tipo' => 'CONSULTA_MEDICA',
                'estado' => 'PROGRAMADA',
                'fecha_hora' => $this->utcDateTime('+5 days', '11:15:00'),
                'profesional' => 'Dra. Carolina Ejemplo',
                'especialidad' => 'Medicina general',
                'centro_medico' => 'Centro Médico Demo',
                'motivo' => 'Próximo control programado',
            ]);

            $losartanId = $this->medication($elenaId, $consultationId, [
                'estado' => 'ACTIVO',
                'nombre' => 'Losartán Demo',
                'dosis' => '50 mg, 1 comprimido',
                'frecuencia' => 'Cada 12 horas',
                'fecha_inicio' => date('Y-m-d', strtotime('-30 days')),
                'fecha_termino' => null,
                'indicaciones' => 'Tomar con agua. Tratamiento completamente ficticio.',
                'horarios' => ['08:00', '20:00'],
            ]);
            $this->medication($mateoId, null, [
                'estado' => 'ACTIVO',
                'nombre' => 'Vitamina D Demo',
                'dosis' => '5 gotas',
                'frecuencia' => 'Una vez al día',
                'fecha_inicio' => date('Y-m-d', strtotime('-10 days')),
                'fecha_termino' => date('Y-m-d', strtotime('+20 days')),
                'indicaciones' => 'Ejemplo de tratamiento con un horario diario.',
                'horarios' => ['09:00'],
            ]);
            $this->medication($elenaId, null, [
                'estado' => 'FINALIZADO',
                'nombre' => 'Tratamiento finalizado Demo',
                'dosis' => '1 cápsula',
                'frecuencia' => 'Cada 8 horas',
                'fecha_inicio' => date('Y-m-d', strtotime('-60 days')),
                'fecha_termino' => date('Y-m-d', strtotime('-53 days')),
                'indicaciones' => 'Tratamiento ficticio ya finalizado.',
                'horarios' => ['08:00', '16:00', '23:00'],
            ]);

            $this->document($elenaId, $consultationId, $userId, [
                'tipo' => 'RECETA',
                'nombre' => 'Receta médica demo.pdf',
                'ruta' => 'demo/receta-medica-demo.pdf',
                'fecha_documento' => date('Y-m-d', strtotime('-14 days')),
                'descripcion' => 'Archivo PDF ficticio para probar la descarga privada.',
                'titulo' => 'Receta médica de demostración',
            ]);
            $this->document($elenaId, $consultationId, $userId, [
                'tipo' => 'INFORME',
                'nombre' => 'Informe de tratamiento demo.pdf',
                'ruta' => 'demo/informe-tratamiento-demo.pdf',
                'fecha_documento' => date('Y-m-d', strtotime('-3 days')),
                'descripcion' => 'Informe ficticio asociado a la atención de origen.',
                'titulo' => 'Informe de tratamiento de demostración',
            ]);

            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function demoUser(): int
    {
        $this->database->exec(
            "INSERT INTO usuarios (nombre, email, google_sub, avatar_url, email_verificado_at, ultimo_acceso_at, activo)
             VALUES ('Usuario Demo', 'demo@example.test', 'demo-google-sub-local', NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1"
        );
        return (int) $this->database->query(
            "SELECT id FROM usuarios WHERE google_sub = 'demo-google-sub-local'"
        )->fetchColumn();
    }

    private function demoFamily(): int
    {
        $this->database->exec(
            "INSERT INTO familias (nombre)
             SELECT 'Familia Demo Completa'
             WHERE NOT EXISTS (SELECT 1 FROM familias WHERE nombre = 'Familia Demo Completa')"
        );
        return (int) $this->database->query(
            "SELECT id FROM familias WHERE nombre = 'Familia Demo Completa' ORDER BY id LIMIT 1"
        )->fetchColumn();
    }

    private function associateUsers(int $familyId, int $demoUserId): void
    {
        $roleId = $this->catalogId('tipos', 'MIEMBRO_FAMILIA', 'ADMINISTRADOR');
        $statement = $this->database->prepare(
            'INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
             VALUES (:familia_id, :usuario_id, :rol_id)
             ON DUPLICATE KEY UPDATE tipo_rol_id = VALUES(tipo_rol_id)'
        );
        $statement->execute(['familia_id' => $familyId, 'usuario_id' => $demoUserId, 'rol_id' => $roleId]);

        $realUserId = $this->database->query(
            "SELECT id FROM usuarios WHERE google_sub <> 'demo-google-sub-local' AND activo = 1
             ORDER BY ultimo_acceso_at DESC, id DESC LIMIT 1"
        )->fetchColumn();
        if ($realUserId !== false) {
            $statement->execute(['familia_id' => $familyId, 'usuario_id' => (int) $realUserId, 'rol_id' => $roleId]);
        }
    }

    private function person(int $familyId, array $data): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO personas
             (familia_id, nombre, identificacion, fecha_nacimiento, grupo_sanguineo, alergias,
              enfermedades_cronicas, contacto_emergencia, telefono_emergencia, observaciones)
             VALUES (:familia_id, :nombre, :identificacion, :fecha_nacimiento, :grupo_sanguineo, :alergias,
                     :enfermedades_cronicas, :contacto_emergencia, :telefono_emergencia, :observaciones)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), fecha_nacimiento = VALUES(fecha_nacimiento),
                grupo_sanguineo = VALUES(grupo_sanguineo), alergias = VALUES(alergias),
                enfermedades_cronicas = VALUES(enfermedades_cronicas), contacto_emergencia = VALUES(contacto_emergencia),
                telefono_emergencia = VALUES(telefono_emergencia), observaciones = VALUES(observaciones)'
        );
        $statement->execute(['familia_id' => $familyId, ...$data]);
        $lookup = $this->database->prepare('SELECT id FROM personas WHERE familia_id = :familia_id AND identificacion = :identificacion');
        $lookup->execute(['familia_id' => $familyId, 'identificacion' => $data['identificacion']]);
        return (int) $lookup->fetchColumn();
    }

    private function attention(int $personId, int $userId, array $data): int
    {
        $marker = '[DEMO:' . $data['marker'] . '] ';
        $lookup = $this->database->prepare('SELECT id FROM atenciones WHERE persona_id = :persona_id AND motivo LIKE :marker LIMIT 1');
        $lookup->execute(['persona_id' => $personId, 'marker' => $marker . '%']);
        $id = $lookup->fetchColumn();
        $values = [
            'persona_id' => $personId,
            'tipo_id' => $this->catalogId('tipos', 'ATENCION', $data['tipo']),
            'estado_id' => $this->catalogId('estados', 'ATENCION', $data['estado']),
            'usuario_id' => $userId,
            'fecha_hora' => $data['fecha_hora'],
            'profesional' => $data['profesional'] ?? null,
            'especialidad' => $data['especialidad'] ?? null,
            'centro_medico' => $data['centro_medico'] ?? null,
            'motivo' => $marker . ($data['motivo'] ?? ''),
            'resultado' => $data['diagnostico_resultado'] ?? null,
            'indicaciones' => $data['indicaciones'] ?? null,
            'temas' => $data['temas_abordados'] ?? null,
            'acuerdos' => $data['acuerdos'] ?? null,
            'proxima_fecha' => $data['proxima_fecha'] ?? null,
        ];
        if ($id === false) {
            $statement = $this->database->prepare(
                'INSERT INTO atenciones
                 (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, profesional, especialidad,
                  centro_medico, motivo, diagnostico_resultado, indicaciones, temas_abordados, acuerdos, proxima_fecha)
                 VALUES (:persona_id, :tipo_id, :estado_id, :usuario_id, :fecha_hora, :profesional, :especialidad,
                         :centro_medico, :motivo, :resultado, :indicaciones, :temas, :acuerdos, :proxima_fecha)'
            );
            $statement->execute($values);
            return (int) $this->database->lastInsertId();
        }
        $statement = $this->database->prepare(
            'UPDATE atenciones SET tipo_id=:tipo_id, estado_id=:estado_id, registrado_por_usuario_id=:usuario_id,
             fecha_hora=:fecha_hora, profesional=:profesional, especialidad=:especialidad, centro_medico=:centro_medico,
             motivo=:motivo, diagnostico_resultado=:resultado, indicaciones=:indicaciones, temas_abordados=:temas,
             acuerdos=:acuerdos, proxima_fecha=:proxima_fecha WHERE id=:id AND persona_id=:persona_id'
        );
        $statement->execute([...$values, 'id' => (int) $id]);
        return (int) $id;
    }

    private function medication(int $personId, ?int $attentionId, array $data): int
    {
        $lookup = $this->database->prepare('SELECT id FROM medicamentos WHERE persona_id = :persona_id AND nombre = :nombre LIMIT 1');
        $lookup->execute(['persona_id' => $personId, 'nombre' => $data['nombre']]);
        $id = $lookup->fetchColumn();
        $values = [
            'persona_id' => $personId,
            'atencion_id' => $attentionId,
            'estado_id' => $this->catalogId('estados', 'MEDICAMENTO', $data['estado']),
            'nombre' => $data['nombre'],
            'dosis' => $data['dosis'],
            'frecuencia' => $data['frecuencia'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_termino' => $data['fecha_termino'],
            'indicaciones' => $data['indicaciones'],
        ];
        if ($id === false) {
            $statement = $this->database->prepare(
                'INSERT INTO medicamentos
                 (persona_id, atencion_id, estado_id, nombre, dosis, frecuencia, fecha_inicio, fecha_termino, indicaciones)
                 VALUES (:persona_id, :atencion_id, :estado_id, :nombre, :dosis, :frecuencia, :fecha_inicio, :fecha_termino, :indicaciones)'
            );
            $statement->execute($values);
            $id = (int) $this->database->lastInsertId();
        } else {
            $statement = $this->database->prepare(
                'UPDATE medicamentos SET atencion_id=:atencion_id, estado_id=:estado_id, dosis=:dosis,
                 frecuencia=:frecuencia, fecha_inicio=:fecha_inicio, fecha_termino=:fecha_termino,
                 indicaciones=:indicaciones WHERE id=:id AND persona_id=:persona_id AND nombre=:nombre'
            );
            $statement->execute([...$values, 'id' => (int) $id]);
        }
        $delete = $this->database->prepare('DELETE FROM medicamento_horarios WHERE medicamento_id = :id');
        $delete->execute(['id' => $id]);
        $insert = $this->database->prepare('INSERT INTO medicamento_horarios (medicamento_id, hora) VALUES (:id, :hora)');
        foreach ($data['horarios'] as $hour) {
            $insert->execute(['id' => $id, 'hora' => $hour . ':00']);
        }
        return (int) $id;
    }

    private function document(int $personId, ?int $attentionId, int $userId, array $data): void
    {
        $storage = require dirname(__DIR__, 2) . '/config/filesystems.php';
        $absolute = rtrim((string) $storage['documents'], '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $data['ruta']);
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, $this->demoPdf($data['titulo']));
        $statement = $this->database->prepare(
            'INSERT INTO documentos
             (persona_id, atencion_id, tipo_id, subido_por_usuario_id, nombre,
              archivo_ruta, mime_type, fecha_documento, descripcion)
             VALUES (:persona_id, :atencion_id, :tipo_id, :usuario_id, :nombre,
                     :ruta, \'application/pdf\', :fecha_documento, :descripcion)
             ON DUPLICATE KEY UPDATE persona_id=VALUES(persona_id), atencion_id=VALUES(atencion_id),
                tipo_id=VALUES(tipo_id), nombre=VALUES(nombre),
                fecha_documento=VALUES(fecha_documento), descripcion=VALUES(descripcion)'
        );
        $statement->execute([
            'persona_id' => $personId,
            'atencion_id' => $attentionId,
            'tipo_id' => $this->catalogId('tipos', 'DOCUMENTO', $data['tipo']),
            'usuario_id' => $userId,
            'nombre' => $data['nombre'],
            'ruta' => $data['ruta'],
            'fecha_documento' => $data['fecha_documento'],
            'descripcion' => $data['descripcion'],
        ]);
    }

    private function catalogId(string $table, string $processCode, string $code): int
    {
        $statement = $this->database->prepare(
            "SELECT c.id FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE p.codigo = :proceso AND c.codigo = :codigo LIMIT 1"
        );
        $statement->execute(['proceso' => $processCode, 'codigo' => $code]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("No existe el catálogo {$processCode}:{$code}.");
        }
        return (int) $id;
    }

    private function demoPdf(string $title): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .,()-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: $title);
        $stream = "BT /F1 18 Tf 72 740 Td ({$text}) Tj 0 -32 Td /F1 11 Tf (Documento ficticio. No contiene informacion medica real.) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            '4 0 obj << /Length ' . strlen($stream) . " >> stream\n{$stream}\nendstream endobj",
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($index = 1; $index <= 5; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        return $pdf . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function utcDateTime(string $relative, string $time): string
    {
        $local = new \DateTimeZone((string) env('APP_TIMEZONE', 'America/Santiago'));
        $date = new \DateTimeImmutable($relative, $local);
        $date = $date->setTime(
            (int) substr($time, 0, 2),
            (int) substr($time, 3, 2),
            (int) substr($time, 6, 2),
        );
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
