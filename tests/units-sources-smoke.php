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
    foreach (['units' => 'unit', 'sources' => 'source'] as $module => $formName) {
        request('/api/'.$module, 401);
        moduleRequest($module, 400, 'POST', ['name' => '']);
        $data = ['name' => 'Test '.$suffix];
        if ('units' === $module) {
            $data['code'] = 'U'.$suffix;
            $data['type'] = 'count';
        }
        $record = moduleRequest($module, 201, 'POST', $data);
        $path = $module.'/'.$record['id'];
        $created[$module] = $path;
        if ('units' === $module) {
            moduleRequest($module, 400, 'POST', $data);
            checkModule(1 === moduleRequest($module.'?q='.$data['code'], 200)['total_item'], 'Code search failed');
        }
        moduleRequest($path, 200);
        moduleRequest($path.'/audit', 200);
        $data['name'] = 'Updated '.$suffix;
        checkModule(moduleRequest($path, 200, 'PUT', $data)['name'] === $data['name'], 'Update failed');
        checkModule(1 === moduleRequest($module.'?q='.rawurlencode($data['name']), 200)['total_item'], 'Name search failed');
        checkModule(0 === moduleRequest($module.'?q=missing'.$suffix, 200)['total_item'], 'Search filtering failed');
    }
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) {
        if (0 === stripos($header, 'Set-Cookie: ')) {
            $cookies[] = explode(';', substr($header, 12))[0];
        }
    }
    $headers = ['Cookie: '.implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    foreach (['units' => 'unit', 'sources' => 'source'] as $module => $formName) {
        request('/admin/'.$created[$module].'/edit', 302, headers: $headers);
        request('/admin/'.$module, 200, headers: $headers);
        $data = ['name' => 'Admin '.$suffix];
        if ('units' === $module) {
            $data['code'] = 'U'.$suffix;
            $data['type'] = 'count';
        }
        request('/admin/'.$module, 303, 'POST', http_build_query([$formName => $data]), $headers);
        [$html] = request('/admin/'.$module, 200, headers: $headers);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        checkModule('' === $dom->getElementById($formName.'_name')->getAttribute('value'), 'Form not reset');
        checkModule(!str_contains($html, 'sas.form.field.'.$formName.'.'), 'Translation missing');
    }
} finally {
    [$login] = request('/api/login', 200, 'POST', json_encode(['username' => 'admin', 'password' => 'admin']), ['Content-Type: application/json']);
    $token = json_decode($login, true, flags: JSON_THROW_ON_ERROR)['token'];
    foreach ($created as $path) {
        moduleRequest($path, 204, 'DELETE');
        moduleRequest($path, 404);
    }
}
echo "Unit and Source checks passed.\n";
