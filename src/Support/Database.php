<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $path = Env::string('QUEUE_DB_PATH', dirname(__DIR__, 2) . '/storage/queue.sqlite');
            $dir = dirname($path);

            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            self::migrate($pdo);

            self::$connection = $pdo;
        }

        return self::$connection;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS review_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                repo TEXT NOT NULL,
                clone_url TEXT NOT NULL,
                pr_number INTEGER NOT NULL,
                base_ref TEXT NOT NULL,
                head_sha TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                error TEXT,
                created_at TEXT NOT NULL,
                started_at TEXT,
                finished_at TEXT,
                UNIQUE(repo, pr_number, head_sha)
            )
            SQL);
    }
}
