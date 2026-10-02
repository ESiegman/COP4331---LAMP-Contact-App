<?php

namespace App\Models;

use PDO;

final class UserModel
{
    private const SORTABLE_COLUMNS = ['First_Name', 'Last_Name', 'Login', 'Role', 'Active'];

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

    public function search(?string $query, ?string $sortBy = null, string $sortDir = 'ASC'): array
    {
        $sql = 'SELECT ID, First_Name, Last_Name, Login, Role, Active FROM Users';
        $params = [];

        if ($query !== null && $query !== '') {
            $sql .= ' WHERE Login LIKE :q OR First_Name LIKE :q OR Last_Name LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY ' . $this->orderBy($sortBy, $sortDir);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function orderBy(?string $sortBy, string $sortDir): string
    {
        if ($sortBy === null || !in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            return 'Login';
        }

        $direction = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        // Secondary keys keep ties in a stable, readable order.
        if ($sortBy === 'Last_Name') {
            return "Last_Name $direction, First_Name $direction, Login ASC";
        }

        return "$sortBy $direction, Login ASC";
    }

    public function disable(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE Users SET Active = 0 WHERE ID = :id');

        return $stmt->execute(['id' => $id]);
    }

    public function enable(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE Users SET Active = 1 WHERE ID = :id');

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
