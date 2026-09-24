<?php

namespace App\Controllers;

use App\Auth\AuthContext;
use App\Models\ContactModel;
use App\Models\UserModel;
use App\Support\Request;
use App\Support\Response;

final class AdminController
{
    public function __construct(
        private UserModel $users,
        private ContactModel $contacts
    ) {
    }

    public function listUsers(AuthContext $auth, Request $request): array
    {
        if (!$auth->isAdmin()) {
            return Response::error('Forbidden', 403);
        }

        return Response::success($this->users->search($request->input('query')));
    }

    public function userContacts(AuthContext $auth, Request $request): array
    {
        if (!$auth->isAdmin()) {
            return Response::error('Forbidden', 403);
        }

        $userId = $request->input('userId');

        if ($userId === null) {
            return Response::error('userId is required', 400);
        }

        return Response::success($this->contacts->search((int) $userId, $request->input('query')));
    }

    public function createUser(AuthContext $auth, Request $request): array
    {
        if (!$auth->isAdmin()) {
            return Response::error('Forbidden', 403);
        }

        $firstName = $request->input('First_Name');
        $lastName = $request->input('Last_Name');
        $login = $request->input('Login');
        $password = $request->input('Password');
        $role = $request->input('Role', 'User');

        if (!$firstName || !$lastName || !$login || !$password) {
            return Response::error('First_Name, Last_Name, Login, and Password are required', 400);
        }

        if (!in_array($role, ['User', 'Admin'], true)) {
            return Response::error('Role must be User or Admin', 400);
        }

        if ($this->users->findByLogin($login) !== null) {
            return Response::error('Login already exists', 409);
        }

        $id = $this->users->create($firstName, $lastName, $login, $password, $role);

        return Response::success(['id' => $id, 'login' => $login, 'role' => $role], 201);
    }

    public function disableUser(AuthContext $auth, Request $request): array
    {
        if (!$auth->isAdmin()) {
            return Response::error('Forbidden', 403);
        }

        $id = (int) $request->input('id');

        if ($this->users->findById($id) === null) {
            return Response::error('User not found', 404);
        }

        $this->users->disable($id);

        return Response::success([]);
    }

    public function changeUserPassword(AuthContext $auth, Request $request): array
    {
        if (!$auth->isAdmin()) {
            return Response::error('Forbidden', 403);
        }

        $id = (int) $request->input('id');
        $newPassword = $request->input('Password');

        if ($this->users->findById($id) === null) {
            return Response::error('User not found', 404);
        }

        if (!$newPassword) {
            return Response::error('Password is required', 400);
        }

        $this->users->updatePassword($id, $newPassword);

        return Response::success([]);
    }
}
