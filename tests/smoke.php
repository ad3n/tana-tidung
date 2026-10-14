<?php

declare(strict_types=1);

// Run against an installation with the bundled development fixtures.
$base = getenv('SMOKE_BASE_URL') ?: 'http://nginx';

function request(string $path, int $expected, string $method = 'GET', string $payload = '', array $headers = []): array
{
    global $base;

    $context = stream_context_create(['http' => [
        'method' => $method,
        'content' => $payload,
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 30,
    ]]);
    $body = file_get_contents($base.$path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matches);
    $status = (int) ($matches[1] ?? 0);
    echo sprintf("%s %s %d\n", $method, $path, $status);

    if ($status !== $expected || false === $body) {
        throw new RuntimeException(sprintf('%s %s: expected HTTP %d, got %d', $method, $path, $expected, $status));
    }

    return [$body, $responseHeaders];
}

foreach (['/health', '/admin/login', '/api/doc', '/favicon.ico'] as $path) {
    request($path, 200);
}
request('/index.php', 404);
request('/.env', 403);
request('/api/me', 401);
[$body] = request('/api/login', 200, 'POST', json_encode([
    'username' => 'admin',
    'password' => 'admin',
], JSON_THROW_ON_ERROR), ['Content-Type: application/json']);
$token = json_decode($body, true, flags: JSON_THROW_ON_ERROR)['token'] ?? null;
if (!is_string($token) || '' === $token) {
    throw new RuntimeException('Login did not return a JWT.');
}

foreach (['/api/me', '/api/me/menus', '/api/groups', '/api/users', '/api/settings'] as $path) {
    request($path, 200, headers: ['Authorization: Bearer '.$token]);
}

[, $headers] = request('/admin/', 302, 'POST', http_build_query([
    '_username' => 'admin',
    '_password' => 'admin',
]), ['Content-Type: application/x-www-form-urlencoded']);
$cookies = [];
foreach ($headers as $header) {
    if (0 === stripos($header, 'Set-Cookie: ')) {
        $cookies[] = explode(';', substr($header, 12))[0];
    }
}

foreach (['/admin/', '/admin/users', '/admin/groups', '/admin/settings'] as $path) {
    request($path, 200, headers: ['Cookie: '.implode('; ', $cookies)]);
}
request('/api/me', 401);
request('/admin/', 302);
echo "HTTP smoke checks passed.\n";
