<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        return $this->session->token();
    }

    public function validate(?string $token): bool
    {
        return $this->session->tokenIsValid($token);
    }
}
