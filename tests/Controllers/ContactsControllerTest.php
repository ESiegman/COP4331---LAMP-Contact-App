<?php

use App\Auth\AuthContext;
use App\Controllers\ContactsController;
use App\Database;
use App\Models\ContactModel;
use App\Models\UserModel;
use App\Support\Request;
use Tests\DatabaseTestCase;

final class ContactsControllerTest extends DatabaseTestCase
{
    private ContactsController $controller;
    private AuthContext $owner;
    private AuthContext $other;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::get();
        $this->controller = new ContactsController(new ContactModel($pdo));

        $users = new UserModel($pdo);
        $ownerId = $users->create('Owner', 'User', 'contacts_owner_' . uniqid(), 'pw12345');
        $otherId = $users->create('Other', 'User', 'contacts_other_' . uniqid(), 'pw12345');

        $this->owner = new AuthContext($ownerId, 'owner', 'User', true);
        $this->other = new AuthContext($otherId, 'other', 'User', true);
    }

    private function create(AuthContext $auth, array $overrides = []): array
    {
        $data = array_merge([
            'First_Name' => 'Jane',
            'Last_Name' => 'Doe',
            'Email' => 'jane@example.com',
            'Phone_Number' => '555-0100',
        ], $overrides);

        return $this->controller->create($auth, new Request('POST', 'contacts.create', $data));
    }

    public function testCreateRequiresAllFields(): void
    {
        $result = $this->controller->create($this->owner, new Request('POST', 'contacts.create', [
            'First_Name' => 'Jane',
        ]));

        $this->assertSame(400, $result['status']);
    }

    public function testCreateSucceedsAndSearchReturnsIt(): void
    {
        $created = $this->create($this->owner, ['First_Name' => 'Findable']);
        $this->assertSame(201, $created['status']);

        $result = $this->controller->search($this->owner, new Request('GET', 'contacts.search', [], ['query' => 'Findable']));
        $names = array_column($result['body']['data'], 'First_Name');

        $this->assertContains('Findable', $names);
    }

    public function testSearchDoesNotLeakOtherUsersContacts(): void
    {
        $this->create($this->other, ['First_Name' => 'Secret']);

        $result = $this->controller->search($this->owner, new Request('GET', 'contacts.search'));
        $names = array_column($result['body']['data'], 'First_Name');

        $this->assertNotContains('Secret', $names);
    }

    public function testGetReturnsOwnContact(): void
    {
        $created = $this->create($this->owner, ['First_Name' => 'Gettable']);
        $id = $created['body']['data']['id'];

        $result = $this->controller->get($this->owner, new Request('GET', 'contacts.get', ['id' => $id]));

        $this->assertSame(200, $result['status']);
        $this->assertSame('Gettable', $result['body']['data']['First_Name']);
    }

    public function testGetRejectsNonOwner(): void
    {
        $created = $this->create($this->owner);
        $id = $created['body']['data']['id'];

        $result = $this->controller->get($this->other, new Request('GET', 'contacts.get', ['id' => $id]));

        $this->assertSame(404, $result['status']);
    }

    public function testGetReturns404ForNonexistentContact(): void
    {
        $result = $this->controller->get($this->owner, new Request('GET', 'contacts.get', ['id' => 999999]));

        $this->assertSame(404, $result['status']);
    }

    public function testUpdateRejectsNonOwner(): void
    {
        $created = $this->create($this->owner);
        $id = $created['body']['data']['id'];

        $result = $this->controller->update($this->other, new Request('PUT', 'contacts.update', [
            'id' => $id, 'First_Name' => 'Hacked',
        ]));

        $this->assertSame(404, $result['status']);
    }

    public function testUpdateSucceedsForOwner(): void
    {
        $created = $this->create($this->owner);
        $id = $created['body']['data']['id'];

        $result = $this->controller->update($this->owner, new Request('PUT', 'contacts.update', [
            'id' => $id, 'First_Name' => 'Updated',
        ]));

        $this->assertSame(200, $result['status']);
    }

    public function testDeleteRejectsNonOwner(): void
    {
        $created = $this->create($this->owner);
        $id = $created['body']['data']['id'];

        $result = $this->controller->delete($this->other, new Request('DELETE', 'contacts.delete', ['id' => $id]));

        $this->assertSame(404, $result['status']);
    }

    public function testDeleteSucceedsForOwner(): void
    {
        $created = $this->create($this->owner);
        $id = $created['body']['data']['id'];

        $result = $this->controller->delete($this->owner, new Request('DELETE', 'contacts.delete', ['id' => $id]));

        $this->assertSame(200, $result['status']);
    }
}
