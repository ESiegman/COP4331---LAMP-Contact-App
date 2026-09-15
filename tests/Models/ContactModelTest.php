<?php

use App\Database;
use App\Models\ContactModel;
use App\Models\UserModel;
use Tests\DatabaseTestCase;

final class ContactModelTest extends DatabaseTestCase
{
    private ContactModel $contacts;
    private int $ownerId;
    private int $otherOwnerId;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::get();
        $this->contacts = new ContactModel($pdo);

        $users = new UserModel($pdo);
        $this->ownerId = $users->create('Owner', 'One', 'contact_owner_' . uniqid(), 'pw12345');
        $this->otherOwnerId = $users->create('Owner', 'Two', 'contact_owner2_' . uniqid(), 'pw12345');
    }

    public function testCreateAndFindById(): void
    {
        $id = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Jane',
            'Last_Name' => 'Doe',
            'Email' => 'jane@example.com',
            'Phone_Number' => '555-0100',
        ]);

        $row = $this->contacts->findById($id);

        $this->assertSame('Jane', $row['First_Name']);
        $this->assertSame($this->ownerId, (int) $row['User_ID']);
    }

    public function testUpdateOnlyTouchesEditableFields(): void
    {
        $id = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Jane',
            'Last_Name' => 'Doe',
            'Email' => 'jane@example.com',
            'Phone_Number' => '555-0100',
        ]);

        $this->contacts->update($id, ['First_Name' => 'Janet', 'ID' => 9999, 'User_ID' => 9999]);

        $row = $this->contacts->findById($id);
        $this->assertSame('Janet', $row['First_Name']);
        $this->assertSame($id, (int) $row['ID']);
        $this->assertSame($this->ownerId, (int) $row['User_ID']);
    }

    public function testDeleteRemovesContact(): void
    {
        $id = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Temp',
            'Last_Name' => 'Contact',
            'Email' => 't@example.com',
            'Phone_Number' => '555-0000',
        ]);

        $this->contacts->delete($id);

        $this->assertNull($this->contacts->findById($id));
    }

    public function testSearchScopedToUserDoesNotReturnOtherUsersContacts(): void
    {
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'Mine',
            'Last_Name' => 'Contact',
            'Email' => 'mine@example.com',
            'Phone_Number' => '555-1111',
        ]);
        $this->contacts->create($this->otherOwnerId, [
            'First_Name' => 'Theirs',
            'Last_Name' => 'Contact',
            'Email' => 'theirs@example.com',
            'Phone_Number' => '555-2222',
        ]);

        $results = $this->contacts->search($this->ownerId, null);
        $names = array_column($results, 'First_Name');

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    public function testSearchByQueryFiltersAcrossFields(): void
    {
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'Unique',
            'Last_Name' => 'Searchable',
            'Email' => 'unique.searchable@example.com',
            'Phone_Number' => '555-4444',
        ]);

        $results = $this->contacts->search($this->ownerId, 'Searchable');

        $this->assertNotEmpty($results);
        $this->assertSame('Unique', $results[0]['First_Name']);
    }
}
