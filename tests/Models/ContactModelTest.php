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

    public function testCreateDefaultsIsFavoriteToFalse(): void
    {
        $id = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Plain',
            'Last_Name' => 'Contact',
            'Email' => 'plain@example.com',
            'Phone_Number' => '555-5555',
        ]);

        $row = $this->contacts->findById($id);

        $this->assertSame(0, (int) $row['Is_Favorite']);
    }

    public function testUpdateCanToggleIsFavorite(): void
    {
        $id = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Fav',
            'Last_Name' => 'Contact',
            'Email' => 'fav@example.com',
            'Phone_Number' => '555-6666',
        ]);

        $this->contacts->update($id, ['Is_Favorite' => 1]);

        $row = $this->contacts->findById($id);
        $this->assertSame(1, (int) $row['Is_Favorite']);
    }

    public function testSearchFavoritesOnlyFilter(): void
    {
        $favId = $this->contacts->create($this->ownerId, [
            'First_Name' => 'Favorited', 'Last_Name' => 'One', 'Email' => 'f1@example.com', 'Phone_Number' => '555-7001',
        ]);
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'NotFavorited', 'Last_Name' => 'Two', 'Email' => 'f2@example.com', 'Phone_Number' => '555-7002',
        ]);
        $this->contacts->update($favId, ['Is_Favorite' => 1]);

        $results = $this->contacts->search($this->ownerId, null, null, 'ASC', true);
        $names = array_column($results, 'First_Name');

        $this->assertContains('Favorited', $names);
        $this->assertNotContains('NotFavorited', $names);
    }

    public function testSearchSortsByExplicitColumnAndDirection(): void
    {
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'Aaron', 'Last_Name' => 'Z', 'Email' => 'a@example.com', 'Phone_Number' => '555-8001',
        ]);
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'Zack', 'Last_Name' => 'A', 'Email' => 'z@example.com', 'Phone_Number' => '555-8002',
        ]);

        $ascending = $this->contacts->search($this->ownerId, null, 'First_Name', 'ASC');
        $descending = $this->contacts->search($this->ownerId, null, 'First_Name', 'DESC');

        $this->assertSame('Aaron', $ascending[0]['First_Name']);
        $this->assertSame('Zack', $descending[0]['First_Name']);
    }

    public function testSearchIgnoresInvalidSortColumn(): void
    {
        $this->contacts->create($this->ownerId, [
            'First_Name' => 'Safe', 'Last_Name' => 'Contact', 'Email' => 's@example.com', 'Phone_Number' => '555-9001',
        ]);

        $results = $this->contacts->search($this->ownerId, null, 'ID; DROP TABLE Contacts;--', 'ASC');

        $this->assertNotEmpty($results);
    }
}
