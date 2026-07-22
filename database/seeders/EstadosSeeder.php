<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;

final class EstadosSeeder
{
    public function run(PDO $database): void
    {
        $rows = [
            ['ATENCION', 'PROGRAMADA', 'Programada', '#3478C8', 10],
            ['ATENCION', 'REALIZADA', 'Realizada', '#2F806B', 20],
            ['ATENCION', 'CANCELADA', 'Cancelada', '#C64B4B', 30],
            ['MEDICAMENTO', 'ACTIVO', 'Activo', '#2F806B', 10],
            ['MEDICAMENTO', 'SUSPENDIDO', 'Suspendido', '#C48738', 20],
            ['MEDICAMENTO', 'FINALIZADO', 'Finalizado', '#64748B', 30],
            ['INVITACION', 'PENDIENTE', 'Pendiente', '#3478C8', 10],
            ['INVITACION', 'ACEPTADA', 'Aceptada', '#2F806B', 20],
            ['INVITACION', 'VENCIDA', 'Vencida', '#64748B', 30],
            ['INVITACION', 'REVOCADA', 'Revocada', '#C64B4B', 40],
        ];

        $process = $database->prepare('SELECT id FROM procesos WHERE codigo = :codigo');
        $statement = $database->prepare(
            'INSERT INTO estados (proceso_id, codigo, nombre, color, orden, activo)
             VALUES (:proceso_id, :codigo, :nombre, :color, :orden, 1)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), color = VALUES(color), orden = VALUES(orden), activo = 1'
        );

        foreach ($rows as [$processCode, $codigo, $nombre, $color, $orden]) {
            $process->execute(['codigo' => $processCode]);
            $procesoId = $process->fetchColumn();
            if ($procesoId === false) {
                throw new \RuntimeException("No existe el proceso {$processCode}");
            }
            $statement->execute([
                'proceso_id' => $procesoId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'color' => $color,
                'orden' => $orden,
            ]);
        }
    }
}
