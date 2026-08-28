<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReviewJobRepository;
use App\Service\PullRequestEligibility;
use App\Service\SignatureValidator;
use App\Support\Env;

final class ReviewWebhookController
{
    public function handle(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_X_SIGNATURE_256'] ?? null;

        $validator = new SignatureValidator(Env::required('HARNESS_SECRET'));
        if (!$validator->isValid($rawBody, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid signature']);

            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid json body']);

            return;
        }

        $eligibility = new PullRequestEligibility(
            Env::string('TARGET_BRANCH', 'main'),
            Env::bool('ALLOW_DRAFTS', false)
        );

        $reason = $eligibility->reasonToSkip($payload);
        if ($reason !== null) {
            http_response_code(200);
            echo json_encode(['queued' => false, 'reason' => $reason]);

            return;
        }

        $required = ['repo', 'clone_url', 'pr_number', 'base_ref', 'head_sha'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "missing field '{$field}'"]);

                return;
            }
        }

        $repository = new ReviewJobRepository();
        $queued = $repository->insert([
            'repo' => (string) $payload['repo'],
            'clone_url' => (string) $payload['clone_url'],
            'pr_number' => (int) $payload['pr_number'],
            'base_ref' => (string) $payload['base_ref'],
            'head_sha' => (string) $payload['head_sha'],
        ]);

        http_response_code(202);
        echo json_encode(['queued' => $queued]);
    }
}
