<?php

namespace App\Repository;

use PDO;

class FileRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $type, string $fileId, ?string $fileName): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO files (user_id, type, file_id, file_name, created_at) VALUES (:user_id, :type, :file_id, :file_name, :created_at)');
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'file_id' => $fileId,
            'file_name' => $fileName,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByUserIdAndType(int $userId, string $type): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE user_id = :user_id AND type = :type LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'type' => $type]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
