<?php

use App\Auth\AuthContext;
use App\Controllers\AuthController;
use App\Database;
use App\Models\UserModel;
use App\Support\Request;
use Tests\DatabaseTestCase;
use Tests\Fakes\InMemoryAuthStore;

final class AuthControllerTest extends DatabaseTestCase
{
    private AuthController $controller;
    private InMemoryAuthStore $authStore;

    protected function setUp(): void
    {
        parent::setUp();
        $users = new UserModel(Database::get());
        $this->authStore = new InMemoryAuthStore();
        $this->controller = new AuthController($users, $this->authStore);
    }

    public function testRegisterCreatesUser(): void
    {
        $login = 'reg_' . uniqid();

        $result = $this->controller->register(new Request('POST', 'auth.register', [
            'First_Name' => 'New',
            'Last_Name' => 'Person',
            'Login' => $login,
            'Password' => 'pw12345',
        ]));

        $this->assertSame(201, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame($login, $result['body']['data']['login']);
    }

    public function testRegisterRejectsMissingFields(): void
    {
        $result = $this->controller->register(new Request('POST', 'auth.register', ['Login' => 'onlylogin']));

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['body']['success']);
    }

    public function testRegisterRejectsDuplicateLogin(): void
    {
        $login = 'dup_' . uniqid();
        $body = ['First_Name' => 'A', 'Last_Name' => 'B', 'Login' => $login, 'Password' => 'pw12345'];

        $this->controller->register(new Request('POST', 'auth.register', $body));
        $result = $this->controller->register(new Request('POST', 'auth.register', $body));

        $this->assertSame(409, $result['status']);
    }

    public function testLoginSucceedsWithValidCredentialsAndStartsSession(): void
    {
        $login = 'loginok_' . uniqid();
        $this->controller->register(new Request('POST', 'auth.register', [
            'First_Name' => 'Log', 'Last_Name' => 'In', 'Login' => $login, 'Password' => 'correctpw',
        ]));

        $result = $this->controller->login(new Request('POST', 'auth.login', [
            'Login' => $login, 'Password' => 'correctpw',
        ]));

        $this->assertSame(200, $result['status']);
        $this->assertSame($login, $result['body']['data']['login']);
        $this->assertNotNull($this->authStore->current());
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $login = 'loginbad_' . uniqid();
        $this->controller->register(new Request('POST', 'auth.register', [
            'First_Name' => 'Log', 'Last_Name' => 'In', 'Login' => $login, 'Password' => 'correctpw',
        ]));

        $result = $this->controller->login(new Request('POST', 'auth.login', [
            'Login' => $login, 'Password' => 'wrongpw',
        ]));

        $this->assertSame(401, $result['status']);
        $this->assertNull($this->authStore->current());
    }

    public function testLoginRejectsDisabledUser(): void
    {
        $users = new UserModel(Database::get());
        $login = 'disabledlogin_' . uniqid();
        $id = $users->create('Dis', 'Abled', $login, 'correctpw');
        $users->disable($id);

        $result = $this->controller->login(new Request('POST', 'auth.login', [
            'Login' => $login, 'Password' => 'correctpw',
        ]));

        $this->assertSame(403, $result['status']);
    }

    public function testLogoutClearsAuthStore(): void
    {
        $this->authStore->start(new AuthContext(1, 'x', 'User', true));

        $this->controller->logout();

        $this->assertNull($this->authStore->current());
    }

    public function testMeReturnsCurrentUserInfo(): void
    {
        $auth = new AuthContext(42, 'someone', 'Admin', true);

        $result = $this->controller->me($auth);

        $this->assertSame(200, $result['status']);
        $this->assertSame(['id' => 42, 'login' => 'someone', 'role' => 'Admin'], $result['body']['data']);
    }

    public function testChangePasswordSucceedsWithCorrectCurrentPassword(): void
    {
        $users = new UserModel(Database::get());
        $login = 'selfpw_' . uniqid();
        $id = $users->create('Self', 'Pw', $login, 'oldpassword');
        $auth = new AuthContext($id, $login, 'User', true);

        $result = $this->controller->changePassword($auth, new Request('PUT', 'auth.password', [
            'CurrentPassword' => 'oldpassword', 'NewPassword' => 'newpassword',
        ]));

        $this->assertSame(200, $result['status']);
        $row = $users->findById($id);
        $this->assertTrue(password_verify('newpassword', $row['Password']));
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $users = new UserModel(Database::get());
        $login = 'selfpwbad_' . uniqid();
        $id = $users->create('Self', 'Pw', $login, 'oldpassword');
        $auth = new AuthContext($id, $login, 'User', true);

        $result = $this->controller->changePassword($auth, new Request('PUT', 'auth.password', [
            'CurrentPassword' => 'wrongpassword', 'NewPassword' => 'newpassword',
        ]));

        $this->assertSame(401, $result['status']);
    }

    public function testChangePasswordRejectsMissingFields(): void
    {
        $auth = new AuthContext(1, 'x', 'User', true);

        $result = $this->controller->changePassword($auth, new Request('PUT', 'auth.password', [
            'CurrentPassword' => 'onlythis',
        ]));

        $this->assertSame(400, $result['status']);
    }
}
