<?php

namespace App\Controllers;

use App\Auth\AuthContext;
use App\Auth\AuthStoreInterface;
use App\Models\UserModel;
use App\Support\Request;
use App\Support\Response;

final class AuthController
{
    public function __construct(
        private UserModel $users,
        private AuthStoreInterface $authStore
    ) {
    }

    public function register(Request $request): array
    {
        $firstName = $request->input('First_Name');
        $lastName = $request->input('Last_Name');
        $login = $request->input('Login');
        $password = $request->input('Password');

        if (!$firstName || !$lastName || !$login || !$password) {
            return Response::error('First_Name, Last_Name, Login, and Password are required', 400);
        }

        if ($this->users->findByLogin($login) !== null) {
            return Response::error('Login already exists', 409);
        }

        $id = $this->users->create($firstName, $lastName, $login, $password);

        return Response::success(['id' => $id, 'login' => $login], 201);
    }

    public function login(Request $request): array
    {
        $login = $request->input('Login');
        $password = $request->input('Password');

        if (!$login || !$password) {
            return Response::error('Login and Password are required', 400);
        }

        $user = $this->users->findByLogin($login);

        if ($user === null || !password_verify($password, $user['Password'])) {
            return Response::error('Invalid credentials', 401);
        }

        if ((int) $user['Active'] !== 1) {
            return Response::error('This account has been disabled', 403);
        }

        $context = new AuthContext((int) $user['ID'], $user['Login'], $user['Role'], true);
        $this->authStore->start($context);

        return Response::success([
            'id' => $context->id,
            'login' => $context->login,
            'role' => $context->role,
        ]);
    }

    public function logout(): array
    {
        $this->authStore->destroy();

        return Response::success([]);
    }

    public function me(AuthContext $auth): array
    {
        return Response::success([
            'id' => $auth->id,
            'login' => $auth->login,
            'role' => $auth->role,
        ]);
    }

    public function changePassword(AuthContext $auth, Request $request): array
    {
        $currentPassword = $request->input('CurrentPassword');
        $newPassword = $request->input('NewPassword');

        if (!$currentPassword || !$newPassword) {
            return Response::error('CurrentPassword and NewPassword are required', 400);
        }

        $user = $this->users->findById($auth->id);

        if ($user === null || !password_verify($currentPassword, $user['Password'])) {
            return Response::error('Current password is incorrect', 401);
        }

        $this->users->updatePassword($auth->id, $newPassword);

        return Response::success([]);
    }
}
