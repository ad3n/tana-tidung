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
    $body = file_get_contents($base . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matches);
    $status = (int) ($matches[1] ?? 0);
    echo sprintf("%s %s %d\n", $method, $path, $status);

    if ($status !== $expected || $body === false) {
        throw new RuntimeException(sprintf('%s %s: expected HTTP %d, got %d', $method, $path, $expected, $status));
    }

    return [$body, $responseHeaders];
}



function moduleRequest(string $path, int $status, string $method = 'GET', array $data = []): array
{
    global $token;
    [$body] = request('/api/' . $path . (str_contains($path, '?') ? '&' : '?') . 'dcq=1', $status, $method,
        $data ? json_encode($data, JSON_THROW_ON_ERROR) : '',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);

    return $body === '' ? [] : json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}

function checkModule(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

[$login] = request('/api/login', 200, 'POST', json_encode(['username' => 'admin', 'password' => 'admin']), ['Content-Type: application/json']);
$token = json_decode($login, true, flags: JSON_THROW_ON_ERROR)['token'];


$list = moduleRequest('fiscal-years?limit=10000', 200)['items'];
$active = array_values(array_filter($list, fn ($year) => $year['status']));
if (!$list) {
    $baseline = moduleRequest('fiscal-years', 201, 'POST', ['year' => 2026, 'status' => false]);
    checkModule($baseline['status'] === true, 'First year must be active');
} else {
    checkModule(count($active) === 1, 'Exactly one baseline active year required');
    $baseline = $active[0];
}
$baselinePath = 'fiscal-years/' . $baseline['id'];
require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new KejawenLab\ApiSkeleton\Kernel('dev', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$testYear = (int) $db->fetchOne('SELECT y FROM generate_series(8000,9999) y WHERE NOT EXISTS (SELECT 1 FROM fiscal_years f WHERE f.year=y) LIMIT 1');
$created = null;
try {
    moduleRequest($baselinePath, 409, 'PUT', ['year' => $baseline['year'], 'status' => false]);
    moduleRequest($baselinePath, 409, 'DELETE');
    $created = moduleRequest('fiscal-years', 201, 'POST', ['year' => $testYear, 'status' => false]);
    checkModule($created['status'] === false, 'New non-first year should remain inactive');
    $path = 'fiscal-years/' . $created['id'];
    moduleRequest($path, 200, 'PUT', ['year' => $testYear, 'status' => true]);
    checkModule(moduleRequest($baselinePath, 200)['status'] === false, 'Previous year still active');
    checkModule((int) $db->fetchOne('SELECT count(*) FROM fiscal_years WHERE status = true AND deleted_at IS NULL') === 1, 'Active count not one');
    moduleRequest($path, 409, 'DELETE');
    moduleRequest($path, 409, 'PUT', ['year' => $testYear, 'status' => false]);
} finally {
    moduleRequest($baselinePath, 200, 'PUT', ['year' => $baseline['year'], 'status' => true]);
    if ($created) {
        moduleRequest('fiscal-years/' . $created['id'], 204, 'DELETE');
    }
}
checkModule((int) $db->fetchOne('SELECT count(*) FROM fiscal_years WHERE status = true AND deleted_at IS NULL') === 1, 'Final active count not one');
echo "Exactly one active fiscal year checks passed.\n";
