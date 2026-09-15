<?php

namespace App\Controllers;

use App\Auth\AuthContext;
use App\Models\ContactModel;
use App\Support\Request;
use App\Support\Response;

final class ContactsController
{
    public function __construct(private ContactModel $contacts)
    {
    }

    public function search(AuthContext $auth, Request $request): array
    {
        $results = $this->contacts->search($auth->id, $request->input('query'));

        return Response::success($results);
    }

    public function get(AuthContext $auth, Request $request): array
    {
        $id = (int) $request->input('id');
        $contact = $this->contacts->findById($id);

        if ($contact === null || (int) $contact['User_ID'] !== $auth->id) {
            return Response::error('Contact not found', 404);
        }

        return Response::success($contact);
    }

    public function create(AuthContext $auth, Request $request): array
    {
        $data = $this->extractFields($request);

        if ($data === null) {
            return Response::error('First_Name, Last_Name, Email, and Phone_Number are required', 400);
        }

        $id = $this->contacts->create($auth->id, $data);

        return Response::success(['id' => $id], 201);
    }

    public function update(AuthContext $auth, Request $request): array
    {
        $id = (int) $request->input('id');
        $contact = $this->contacts->findById($id);

        if ($contact === null || (int) $contact['User_ID'] !== $auth->id) {
            return Response::error('Contact not found', 404);
        }

        $data = $this->extractFields($request, required: false);
        $this->contacts->update($id, $data);

        return Response::success(['id' => $id]);
    }

    public function delete(AuthContext $auth, Request $request): array
    {
        $id = (int) $request->input('id');
        $contact = $this->contacts->findById($id);

        if ($contact === null || (int) $contact['User_ID'] !== $auth->id) {
            return Response::error('Contact not found', 404);
        }

        $this->contacts->delete($id);

        return Response::success([]);
    }

    private function extractFields(Request $request, bool $required = true): ?array
    {
        $fields = ['First_Name', 'Last_Name', 'Email', 'Phone_Number'];
        $data = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        if ($required && count($data) < count($fields)) {
            return null;
        }

        return $data;
    }
}
