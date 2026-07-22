<?php

declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}
