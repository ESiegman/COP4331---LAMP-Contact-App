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
