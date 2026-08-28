<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;
use PDO;
use Throwable;

final class ReviewJobRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * @param array{repo: string, clone_url: string, pr_number: int, base_ref: string, head_sha: string} $job
     */
    public function insert(array $job): bool
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT OR IGNORE INTO review_jobs
                (repo, clone_url, pr_number, base_ref, head_sha, status, created_at)
            VALUES
                (:repo, :clone_url, :pr_number, :base_ref, :head_sha, 'pending', :created_at)
            SQL);

        $stmt->execute([
            'repo' => $job['repo'],
            'clone_url' => $job['clone_url'],
            'pr_number' => $job['pr_number'],
            'base_ref' => $job['base_ref'],
            'head_sha' => $job['head_sha'],
            'created_at' => gmdate('c'),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $job = $this->pdo
                ->query("SELECT * FROM review_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC);

            if ($job === false) {
                $this->pdo->commit();

                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE review_jobs SET status = 'processing', started_at = :started_at WHERE id = :id AND status = 'pending'"
            );
            $update->execute(['started_at' => gmdate('c'), 'id' => $job['id']]);

            if ($update->rowCount() === 0) {
                $this->pdo->commit();

                return null;
            }

            $this->pdo->commit();
            $job['status'] = 'processing';

            return $job;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function complete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE review_jobs SET status = 'done', finished_at = :finished_at WHERE id = :id"
        );
        $stmt->execute(['finished_at' => gmdate('c'), 'id' => $id]);
    }

    public function fail(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE review_jobs SET status = 'failed', error = :error, finished_at = :finished_at WHERE id = :id"
        );
        $stmt->execute(['error' => $error, 'finished_at' => gmdate('c'), 'id' => $id]);
    }
}
