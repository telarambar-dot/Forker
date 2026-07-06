<?php

namespace App\Database;

use PDO;

class Connection
{
    private static ?PDO $pdo = null;

    public static function make(string $databasePath): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::migrate($pdo);
        self::$pdo = $pdo;

        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->beginTransaction();

        try {
            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_id TEXT NOT NULL UNIQUE,
    first_name TEXT,
    last_name TEXT,
    national_code TEXT UNIQUE,
    postal_code TEXT,
    address TEXT,
    referee_name TEXT,
    referee_phone TEXT,
    registration_status TEXT NOT NULL DEFAULT 'draft',
    verification_status TEXT NOT NULL DEFAULT 'draft',
    is_verified INTEGER NOT NULL DEFAULT 0,
    is_admin INTEGER NOT NULL DEFAULT 0,
    rejected_reason TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS registration_states (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    step TEXT NOT NULL,
    pending_text TEXT,
    pending_file_id TEXT,
    pending_file_name TEXT,
    context TEXT,
    updated_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    file_id TEXT NOT NULL,
    file_name TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS workflow_states (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    step TEXT NOT NULL,
    context TEXT,
    data TEXT,
    updated_at TEXT NOT NULL
);
SQL
            );

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
