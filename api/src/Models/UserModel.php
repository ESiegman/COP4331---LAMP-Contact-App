<?php

namespace App\Models;

use PDO;

final class UserModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(
        string $firstName,
        string $lastName,
        string $login,
        string $plainPassword,
        string $role = 'User'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO Users (First_Name, Last_Name, Login, Password, Role) VALUES (:first, :last, :login, :password, :role)'
        );
        $stmt->execute([
            'first' => $firstName,
            'last' => $lastName,
            'login' => $login,
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'role' => $role === 'Admin' ? 'Admin' : 'User',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Users WHERE Login = :login');
        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Users WHERE ID = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function search(?string $query): array
    {
        if ($query === null || $query === '') {
            $stmt = $this->pdo->query(
                'SELECT ID, First_Name, Last_Name, Login, Role, Active FROM Users ORDER BY Login'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare(
            'SELECT ID, First_Name, Last_Name, Login, Role, Active FROM Users
             WHERE Login LIKE :q OR First_Name LIKE :q OR Last_Name LIKE :q
             ORDER BY Login'
        );
        $stmt->execute(['q' => '%' . $query . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function disable(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE Users SET Active = 0 WHERE ID = :id');

        return $stmt->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $newPlainPassword): bool
    {
        $stmt = $this->pdo->prepare('UPDATE Users SET Password = :password WHERE ID = :id');

        return $stmt->execute([
            'password' => password_hash($newPlainPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }
}
