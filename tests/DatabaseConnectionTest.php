<?php

use PHPUnit\Framework\TestCase;
use App\Database;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnectsAndSeesSeededTestUsers(): void
    {
        $pdo = Database::get();

        $stmt = $pdo->query("SELECT Login, Role FROM Users ORDER BY Login");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $logins = array_column($rows, 'Login');

        $this->assertContains('test_admin', $logins);
        $this->assertContains('test_user', $logins);
    }

    public function testAdminRoleIsSetForSeededAdminUser(): void
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare("SELECT Role FROM Users WHERE Login = :login");
        $stmt->execute(['login' => 'test_admin']);
        $role = $stmt->fetchColumn();

        $this->assertSame('Admin', $role);
    }
}
