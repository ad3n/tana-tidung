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


require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new KejawenLab\ApiSkeleton\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$year = (int) $em->getConnection()->fetchOne('SELECT y FROM generate_series(8000,9999) y WHERE NOT EXISTS (SELECT 1 FROM fiscal_years f WHERE f.year=y) LIMIT 1');
$baseline = array_values(array_filter(moduleRequest('fiscal-years?limit=10000', 200)['items'], fn ($item) => $item['status']))[0];
$path = null;
try {
    request('/api/fiscal-years', 401);
    foreach (['', 'abc', 999, 10000, '2026.5'] as $invalid) {
        moduleRequest('fiscal-years', 400, 'POST', ['year' => $invalid]);
    }
    $record = moduleRequest('fiscal-years', 201, 'POST', ['year' => $year]);
    $path = 'fiscal-years/' . $record['id'];
    checkModule($record['year'] === $year && $record['status'] === false, 'Default fiscal year invalid');
    moduleRequest('fiscal-years', 400, 'POST', ['year' => $year]);
    checkModule(moduleRequest($path, 200, 'PUT', ['year' => $year, 'status' => true])['status'] === true, 'Activation failed');
    moduleRequest($path, 409, 'PUT', ['year' => $year, 'status' => false]);
    moduleRequest('fiscal-years/' . $baseline['id'], 200, 'PUT', ['year' => $baseline['year'], 'status' => true]);
    moduleRequest($path . '/audit', 200);
    checkModule(moduleRequest('fiscal-years?q=' . $year, 200)['total_item'] === 1, 'Year search failed');
    checkModule(moduleRequest('fiscal-years?q=invalid', 200)['total_item'] === 0, 'Search filtering failed');
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: ') === 0) $cookies[] = explode(';', substr($header, 12))[0];
    }
    $headers = ['Cookie: ' . implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    request('/admin/' . $path . '/edit', 302, headers: $headers);
    [$html] = request('/admin/fiscal-years', 200, headers: $headers);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    checkModule($xpath->query('//li[contains(@class,"has-treeview")][a/p[contains(text(),"Administrator")]]//a[@href="/admin/fiscal-years"]')->length === 1, 'Fiscal year missing from Administrator');
    $yearInput = $xpath->query('//input[contains(@name,"[year]")]')->item(0);
    $formName = explode('[', $yearInput->getAttribute('name'))[0];
    request('/admin/fiscal-years', 303, 'POST', http_build_query([$formName => ['year' => $year, 'status' => '1']]), $headers);
    [$html] = request('/admin/fiscal-years', 200, headers: $headers);
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    checkModule($xpath->query('//input[contains(@name,"[year]")]')->item(0)->getAttribute('value') === '', 'Form did not reset');
    checkModule(str_contains($html, 'Daftar Tahun Anggaran'), 'Translation missing');
} finally {
    if ($path) {
        [$login] = request('/api/login', 200, 'POST', json_encode(['username' => 'admin', 'password' => 'admin']), ['Content-Type: application/json']);
        $token = json_decode($login, true, flags: JSON_THROW_ON_ERROR)['token'];
        moduleRequest('fiscal-years/' . $baseline['id'], 200, 'PUT', ['year' => $baseline['year'], 'status' => true]);
        moduleRequest($path, 204, 'DELETE');
        moduleRequest($path, 404);
    }
}
echo "Fiscal Year checks passed.\n";
