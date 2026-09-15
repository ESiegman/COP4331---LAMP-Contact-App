<?php

use App\Auth\AuthContext;
use App\Controllers\AdminController;
use App\Database;
use App\Models\ContactModel;
use App\Models\UserModel;
use App\Support\Request;
use Tests\DatabaseTestCase;

final class AdminControllerTest extends DatabaseTestCase
{
    private AdminController $controller;
    private UserModel $users;
    private AuthContext $admin;
    private AuthContext $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::get();
        $this->users = new UserModel($pdo);
        $this->controller = new AdminController($this->users, new ContactModel($pdo));

        $adminId = $this->users->create('Admin', 'Person', 'admin_ctrl_' . uniqid(), 'pw12345');
        $pdo->prepare('UPDATE Users SET Role = ? WHERE ID = ?')->execute(['Admin', $adminId]);

        $userId = $this->users->create('Regular', 'Person', 'user_ctrl_' . uniqid(), 'pw12345');

        $this->admin = new AuthContext($adminId, 'admin', 'Admin', true);
        $this->regularUser = new AuthContext($userId, 'user', 'User', true);
    }

    public function testNonAdminCannotListUsers(): void
    {
        $result = $this->controller->listUsers($this->regularUser, new Request('GET', 'admin.users.search'));

        $this->assertSame(403, $result['status']);
    }

    public function testAdminCanListUsers(): void
    {
        $result = $this->controller->listUsers($this->admin, new Request('GET', 'admin.users.search'));

        $this->assertSame(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']);
    }

    public function testNonAdminCannotDisableUser(): void
    {
        $result = $this->controller->disableUser($this->regularUser, new Request('PUT', 'admin.users.disable', [
            'id' => $this->regularUser->id,
        ]));

        $this->assertSame(403, $result['status']);
    }

    public function testAdminCanDisableAnyUserIncludingOtherAdmins(): void
    {
        $otherAdminId = $this->users->create('Other', 'Admin', 'other_admin_' . uniqid(), 'pw12345');
        $pdo = Database::get();
        $pdo->prepare('UPDATE Users SET Role = ? WHERE ID = ?')->execute(['Admin', $otherAdminId]);

        $result = $this->controller->disableUser($this->admin, new Request('PUT', 'admin.users.disable', [
            'id' => $otherAdminId,
        ]));

        $this->assertSame(200, $result['status']);
        $this->assertSame(0, (int) $this->users->findById($otherAdminId)['Active']);
    }

    public function testAdminCanChangeUserPasswordAndUserCanLoginWithNewOne(): void
    {
        $result = $this->controller->changeUserPassword($this->admin, new Request('PUT', 'admin.users.password', [
            'id' => $this->regularUser->id, 'Password' => 'brandnewpassword',
        ]));

        $this->assertSame(200, $result['status']);

        $row = $this->users->findById($this->regularUser->id);
        $this->assertTrue(password_verify('brandnewpassword', $row['Password']));
    }

    public function testUserContactsSearchIsAdminOnly(): void
    {
        $result = $this->controller->userContacts($this->regularUser, new Request('GET', 'admin.users.contacts'));

        $this->assertSame(403, $result['status']);
    }
}
