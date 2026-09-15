<?php

namespace App\Auth;

final class AuthContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $login,
        public readonly string $role,
        public readonly bool $active
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }
}
