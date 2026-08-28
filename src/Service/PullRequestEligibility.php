<?php

declare(strict_types=1);

namespace App\Service;

final class PullRequestEligibility
{
    private const ALLOWED_ACTIONS = ['opened', 'reopened', 'synchronize'];

    public function __construct(
        private readonly string $targetBranch,
        private readonly bool $allowDrafts,
    ) {
    }

    public function reasonToSkip(array $payload): ?string
    {
        $action = $payload['action'] ?? null;
        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            return "action '{$action}' is not reviewable";
        }

        $baseRef = $payload['base_ref'] ?? null;
        if ($baseRef !== $this->targetBranch) {
            return "base_ref '{$baseRef}' is not the target branch '{$this->targetBranch}'";
        }

        if (!$this->allowDrafts && ($payload['draft'] ?? false) === true) {
            return 'draft PRs are not reviewed';
        }

        return null;
    }
}
