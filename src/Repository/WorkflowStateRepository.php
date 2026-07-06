<?php

namespace App\Repository;

use PDO;

class WorkflowStateRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByActorId(string $actorId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workflow_states WHERE actor_id = :actor_id');
        $stmt->execute(['actor_id' => $actorId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $actorId, string $type, string $step, ?string $context = null, ?string $data = null): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO workflow_states (actor_id, type, step, context, data, updated_at) VALUES (:actor_id, :type, :step, :context, :data, :updated_at)');
        $stmt->execute([
            'actor_id' => $actorId,
            'type' => $type,
            'step' => $step,
            'context' => $context,
            'data' => $data,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(string $actorId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $allowed = ['type', 'step', 'context', 'data'];
        $updates = [];
        $params = ['actor_id' => $actorId];
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

        $sql = sprintf('UPDATE workflow_states SET %s WHERE actor_id = :actor_id', implode(', ', $updates));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteByActorId(string $actorId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM workflow_states WHERE actor_id = :actor_id');
        $stmt->execute(['actor_id' => $actorId]);
    }
}
