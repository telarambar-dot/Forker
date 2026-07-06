<?php

namespace App\Repository;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByExternalId(string $externalId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE external_id = :external_id');
        $stmt->execute(['external_id' => $externalId]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }

    public function create(string $externalId, bool $isAdmin = false): array
    {
        $sql = 'INSERT INTO users (external_id, is_admin, created_at, updated_at) VALUES (:external_id, :is_admin, :created_at, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt->execute([
            'external_id' => $externalId,
            'is_admin' => $isAdmin ? 1 : 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function update(int $id, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $allowed = [
            'first_name',
            'last_name',
            'national_code',
            'postal_code',
            'address',
            'referee_name',
            'referee_phone',
            'registration_status',
            'verification_status',
            'is_verified',
            'is_admin',
            'rejected_reason',
        ];

        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $updates[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        if (empty($updates)) {
            return;
        }

        $params['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $updates[] = 'updated_at = :updated_at';

        $sql = sprintf('UPDATE users SET %s WHERE id = :id', implode(', ', $updates));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function getPendingApplications(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE registration_status = 'pending' AND verification_status = 'waiting_admin' ORDER BY updated_at DESC");
        return $stmt->fetchAll();
    }
}
