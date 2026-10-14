<?php

declare(strict_types=1);

// Uses the development fixture account and removes records created by this check.
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

function moduleRequest(string $path, int $status, string $method = 'GET', array $data = []): array
{
    global $token;
    [$body] = request('/api/'.$path.(str_contains($path, '?') ? '&' : '?').'dcq=1', $status, $method,
        $data ? json_encode($data, JSON_THROW_ON_ERROR) : '',
        ['Content-Type: application/json', 'Authorization: Bearer '.$token]);

    return '' === $body ? [] : json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}

function checkModule(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

[$login] = request('/api/login', 200, 'POST', json_encode(['username' => 'admin', 'password' => 'admin']), ['Content-Type: application/json']);
$token = json_decode($login, true, flags: JSON_THROW_ON_ERROR)['token'];

$suffix = strtoupper(bin2hex(random_bytes(5)));
$created = [];
try {
    foreach (['levels', 'organizations'] as $module) {
        request('/api/'.$module, 401);

        moduleRequest($module, 400, 'POST', ['name' => '']);
    }
    $level = moduleRequest('levels', 201, 'POST', ['name' => 'Level '.$suffix]);
    $created[] = 'levels/'.$level['id'];
    $updated = moduleRequest(end($created), 200, 'PUT', ['name' => 'Updated '.$suffix]);
    checkModule($updated['name'] === 'Updated '.$suffix, 'Level update failed');
    $levels = moduleRequest('levels?q='.$suffix, 200);
    checkModule(1 === count($levels['items']), 'Level search failed');
    checkModule(0 === moduleRequest('levels?q=missing'.$suffix, 200)['total_item'], 'Level filtering failed');
    $rootData = ['code' => 'R'.$suffix, 'name' => 'Root '.$suffix];
    $root = moduleRequest('organizations', 201, 'POST', $rootData);
    $created[] = 'organizations/'.$root['id'];
    $childData = ['code' => 'C'.$suffix, 'name' => 'Child '.$suffix, 'parent' => $root['id']];
    $child = moduleRequest('organizations', 201, 'POST', $childData);
    $created[] = 'organizations/'.$child['id'];
    checkModule($child['parent']['id'] === $root['id'], 'Parent relation failed');
    moduleRequest('organizations', 400, 'POST', $rootData);
    moduleRequest('organizations/'.$root['id'], 400, 'PUT', $rootData + ['parent' => $root['id']]);
    moduleRequest('organizations/'.$root['id'], 400, 'PUT', $rootData + ['parent' => $child['id']]);
    $stored = moduleRequest('organizations/'.$root['id'], 200);
    checkModule(($stored['parent'] ?? null) === null, 'Invalid hierarchy was persisted');
    checkModule(1 === moduleRequest('organizations?q=C'.$suffix, 200)['total_item'], 'Code search failed');
    checkModule(1 === moduleRequest('organizations?q='.rawurlencode('Root '.$suffix), 200)['total_item'], 'Name search failed');
    $childData['parent'] = null;
    $detached = moduleRequest('organizations/'.$child['id'], 200, 'PUT', $childData);
    checkModule(($detached['parent'] ?? null) === null, 'Clearing parent failed');
} finally {
    foreach (array_reverse($created) as $path) {
        moduleRequest($path, 204, 'DELETE');
        moduleRequest($path, 404);
    }
}
echo "Level and Organization checks passed.\n";
