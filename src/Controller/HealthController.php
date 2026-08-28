<?php

declare(strict_types=1);

namespace App\Controller;

final class HealthController
{
    public function handle(): void
    {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    }
}
