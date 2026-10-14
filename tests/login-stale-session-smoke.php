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



require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new KejawenLab\ApiSkeleton\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$cookies = [];
function browserRequest(string $path, int $status, string $method = 'GET', array $data = []): array
{
    global $cookies;
    [$body, $headers] = request($path, $status, $method, http_build_query($data), [
        'Cookie: ' . implode('; ', $cookies),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $m)) $cookies[$m[1]] = $m[1] . '=' . $m[2];
    }
    $location = null;
    foreach ($headers as $header) if (stripos($header, 'Location: ') === 0) $location = trim(substr($header, 10));
    return [$body, $location, $headers];
}
$credentials = ['_username' => 'admin', '_password' => 'admin'];
browserRequest('/admin/', 302, 'POST', $credentials);
browserRequest('/admin/', 200);
// Simulate another login superseding the browser session, without a timing dependency.
$em->getConnection()->executeStatement('UPDATE core_user SET device_id = ? WHERE username = ?', [bin2hex(random_bytes(32)), 'admin']);
$em->getConfiguration()->getResultCache()->clear();
[, $location] = browserRequest('/admin/', 302, 'POST', $credentials);
if (parse_url($location, PHP_URL_PATH) !== '/admin/') throw new RuntimeException('First login submit was intercepted: ' . $location);
browserRequest('/admin/', 200);
echo "Stale-session login succeeds on first submit.\n";
$invalidate = static function () use ($em): void {
    $em->getConnection()->executeStatement('UPDATE core_user SET device_id = ? WHERE username = ?', [bin2hex(random_bytes(32)), 'admin']);
    $em->getConfiguration()->getResultCache()->clear();
};
$invalidate();
[, $location] = browserRequest('/admin/', 302);
if (parse_url($location, PHP_URL_PATH) !== '/admin/login') throw new RuntimeException('Stale protected page did not redirect to login');
[, , $headers] = browserRequest('/admin/login', 200);
if (!str_contains(strtolower(implode('\n', $headers)), 'no-store')) throw new RuntimeException('Login page may be cached');
browserRequest('/admin/', 302, 'POST', $credentials);
browserRequest('/admin/', 200);
$invalidate();
[, $location] = browserRequest('/admin/', 302, 'POST', ['_username' => 'admin', '_password' => 'invalid-password']);
if (parse_url($location, PHP_URL_PATH) !== '/admin/login') throw new RuntimeException('Invalid credentials not rejected');
browserRequest('/admin/', 302);
browserRequest('/admin/', 302, 'POST', $credentials);
browserRequest('/admin/', 200);
$invalidate();
browserRequest('/admin/login', 200);
browserRequest('/admin/', 302, 'POST', $credentials);
browserRequest('/admin/', 200);
echo "Protected routes, invalid password, and login-page recovery checks passed.\n";
