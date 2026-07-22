<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(protected readonly Database $database)
    {
    }
}
