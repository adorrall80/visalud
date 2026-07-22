<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;

final class ProcesosSeeder
{
    public function run(PDO $database): void
    {
        $rows = [
            ['MIEMBRO_FAMILIA', 'Miembro de familia'],
            ['ATENCION', 'Atención'],
            ['MEDICAMENTO', 'Medicamento'],
            ['DOCUMENTO', 'Documento'],
            ['INVITACION', 'Invitación familiar'],
        ];

        $statement = $database->prepare(
            'INSERT INTO procesos (codigo, nombre, activo)
             VALUES (:codigo, :nombre, 1)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1'
        );

        foreach ($rows as [$codigo, $nombre]) {
            $statement->execute(compact('codigo', 'nombre'));
        }
    }
}
