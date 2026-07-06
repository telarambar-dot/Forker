<?php

namespace App\Repository;

use PDO;

class RegistrationStateRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registration_states WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(int $userId, string $step, ?string $pendingText = null, ?string $pendingFileId = null, ?string $pendingFileName = null, ?string $context = null): void
    {
        $sql = 'INSERT OR REPLACE INTO registration_states (user_id, step, pending_text, pending_file_id, pending_file_name, context, updated_at) VALUES (:user_id, :step, :pending_text, :pending_file_id, :pending_file_name, :context, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'step' => $step,
            'pending_text' => $pendingText,
            'pending_file_id' => $pendingFileId,
            'pending_file_name' => $pendingFileName,
            'context' => $context,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $userId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $allowed = ['step', 'pending_text', 'pending_file_id', 'pending_file_name', 'context'];
        $updates = [];
        $params = ['user_id' => $userId];
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

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = sprintf('UPDATE registration_states SET %s WHERE user_id = :user_id', implode(', ', $updates));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteByUserId(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM registration_states WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
