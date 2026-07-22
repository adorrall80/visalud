<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;

final class TiposSeeder
{
    public function run(PDO $database): void
    {
        $rows = [
            ['MIEMBRO_FAMILIA', 'ADMINISTRADOR', 'Administrador', 10],
            ['MIEMBRO_FAMILIA', 'FAMILIAR', 'Familiar', 20],
            ['ATENCION', 'CONSULTA_MEDICA', 'Consulta médica', 10],
            ['ATENCION', 'TERAPIA', 'Terapia', 20],
            ['ATENCION', 'EXAMEN', 'Examen', 30],
            ['DOCUMENTO', 'ORDEN_MEDICA', 'Orden médica', 10],
            ['DOCUMENTO', 'RECETA', 'Receta', 20],
            ['DOCUMENTO', 'RESULTADO_EXAMEN', 'Resultado de examen', 30],
            ['DOCUMENTO', 'INFORME', 'Informe', 40],
            ['DOCUMENTO', 'CERTIFICADO', 'Certificado', 50],
            ['DOCUMENTO', 'OTRO', 'Otro', 60],
        ];

        $process = $database->prepare('SELECT id FROM procesos WHERE codigo = :codigo');
        $statement = $database->prepare(
            'INSERT INTO tipos (proceso_id, codigo, nombre, orden, activo)
             VALUES (:proceso_id, :codigo, :nombre, :orden, 1)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), orden = VALUES(orden), activo = 1'
        );

        foreach ($rows as [$processCode, $codigo, $nombre, $orden]) {
            $process->execute(['codigo' => $processCode]);
            $procesoId = $process->fetchColumn();
            if ($procesoId === false) {
                throw new \RuntimeException("No existe el proceso {$processCode}");
            }
            $statement->execute([
                'proceso_id' => $procesoId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'orden' => $orden,
            ]);
        }
    }
}
