<?php

namespace App\Auth;

final class SessionAuthStore implements AuthStoreInterface
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function start(AuthContext $context): void
    {
        $_SESSION['user'] = [
            'id' => $context->id,
            'login' => $context->login,
            'role' => $context->role,
            'active' => $context->active,
        ];
    }

    public function current(): ?AuthContext
    {
        if (!isset($_SESSION['user'])) {
            return null;
        }

        $user = $_SESSION['user'];

        return new AuthContext($user['id'], $user['login'], $user['role'], $user['active']);
    }

    public function destroy(): void
    {
        unset($_SESSION['user']);
    }
}
