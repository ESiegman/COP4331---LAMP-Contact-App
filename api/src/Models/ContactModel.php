<?php

namespace App\Models;

use PDO;

final class ContactModel
{
    private const EDITABLE_FIELDS = ['First_Name', 'Last_Name', 'Email', 'Phone_Number'];

    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO Contacts (First_Name, Last_Name, Email, Phone_Number, User_ID)
             VALUES (:first, :last, :email, :phone, :userId)'
        );
        $stmt->execute([
            'first' => $data['First_Name'],
            'last' => $data['Last_Name'],
            'email' => $data['Email'],
            'phone' => $data['Phone_Number'],
            'userId' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Contacts WHERE ID = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));

        if (empty($fields)) {
            return false;
        }

        $set = implode(', ', array_map(fn ($field) => "$field = :$field", array_keys($fields)));
        $fields['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE Contacts SET $set WHERE ID = :id");

        return $stmt->execute($fields);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM Contacts WHERE ID = :id');

        return $stmt->execute(['id' => $id]);
    }

    public function search(int $userId, ?string $query): array
    {
        $sql = 'SELECT * FROM Contacts WHERE User_ID = :userId';
        $params = ['userId' => $userId];

        if ($query !== null && $query !== '') {
            $sql .= ' AND (First_Name LIKE :q OR Last_Name LIKE :q OR Email LIKE :q OR Phone_Number LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY Last_Name, First_Name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
