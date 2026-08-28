<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controller\HealthController;
use App\Controller\ReviewWebhookController;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($method === 'GET' && $path === '/health') {
    (new HealthController())->handle();

    return;
}

if ($method === 'POST' && $path === '/webhook/review') {
    (new ReviewWebhookController())->handle();

    return;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);
