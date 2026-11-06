<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health' || $path === '/health/') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'service' => 'brewsbook-api',
        'env' => getenv('APP_ENV') ?: 'local',
        'auth' => 'not_validated_yet',
        'timestamp' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(200);
echo json_encode([
    'service' => 'brewsbook-api',
    'message' => 'CodeIgniter stub — replace with real app source',
    'endpoints' => ['/health'],
], JSON_UNESCAPED_SLASHES);
