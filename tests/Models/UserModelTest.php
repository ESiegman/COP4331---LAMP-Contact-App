<?php

use App\Database;
use App\Models\UserModel;
use Tests\DatabaseTestCase;

final class UserModelTest extends DatabaseTestCase
{
    private UserModel $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new UserModel(Database::get());
    }

    public function testCreateHashesPasswordAndDefaultsToUserRole(): void
    {
        $login = 'test_create_' . uniqid();
        $id = $this->users->create('New', 'User', $login, 'plaintext123');

        $row = $this->users->findById($id);

        $this->assertNotNull($row);
        $this->assertSame($login, $row['Login']);
        $this->assertSame('User', $row['Role']);
        $this->assertSame(1, (int) $row['Active']);
        $this->assertNotSame('plaintext123', $row['Password']);
        $this->assertTrue(password_verify('plaintext123', $row['Password']));
    }

    public function testFindByLoginReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->users->findByLogin('does_not_exist_' . uniqid()));
    }

    public function testSearchFiltersByLoginOrName(): void
    {
        $login = 'searchable_' . uniqid();
        $this->users->create('Searchable', 'Person', $login, 'pw12345');

        $results = $this->users->search('Searchable');
        $logins = array_column($results, 'Login');

        $this->assertContains($login, $logins);
    }

    public function testSearchWithNoQueryReturnsAllUsers(): void
    {
        $results = $this->users->search(null);

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function testDisableSetsActiveToZero(): void
    {
        $login = 'disableme_' . uniqid();
        $id = $this->users->create('Disable', 'Me', $login, 'pw12345');

        $this->users->disable($id);

        $row = $this->users->findById($id);
        $this->assertSame(0, (int) $row['Active']);
    }

    public function testUpdatePasswordChangesHash(): void
    {
        $login = 'pwchange_' . uniqid();
        $id = $this->users->create('Pw', 'Change', $login, 'oldpassword');
        $oldHash = $this->users->findById($id)['Password'];

        $this->users->updatePassword($id, 'newpassword');

        $row = $this->users->findById($id);
        $this->assertNotSame($oldHash, $row['Password']);
        $this->assertTrue(password_verify('newpassword', $row['Password']));
        $this->assertFalse(password_verify('oldpassword', $row['Password']));
    }
}
