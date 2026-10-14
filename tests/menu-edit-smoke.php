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

$code = 'TEST' . strtoupper(bin2hex(random_bytes(4)));
$repo = $em->getRepository(KejawenLab\ApiSkeleton\Entity\Menu::class);
$menu = new KejawenLab\ApiSkeleton\Entity\Menu();
$menu->setCode($code);
$menu->setName('Test menu');
$em->persist($menu);
$em->flush();
try {
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: ') === 0) {
            $cookies[] = explode(';', substr($header, 12))[0];
        }
    }
    $headers = ['Cookie: ' . implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    request('/admin/levels', 200, headers: $headers);
    request('/admin/menus/' . $menu->getId() . '/edit', 302, headers: $headers);
    request('/admin/menus', 200, headers: $headers);
    $data = ['code' => $code, 'name' => 'Updated test', 'routeName' => '#', 'sortOrder' => 0, 'showable' => 1];
    request('/admin/menus', 303, 'POST', http_build_query(['menu' => $data]), $headers);
    $data['code'] = $code . 'NEW';
    $data['name'] = 'New test';
    request('/admin/menus', 303, 'POST', http_build_query(['menu' => $data]), $headers);
    $em->clear();
    if (!$repo->findOneBy(['code' => $code]) || !$repo->findOneBy(['code' => $code . 'NEW'])) {
        throw new RuntimeException('Adding after editing overwrote the previous menu.');
    }
    echo "Menu edit then add regression passed.\n";
} finally {
    $em->clear();
    foreach ([$code, $code . 'NEW'] as $testCode) {
        if ($testMenu = $repo->findOneBy(['code' => $testCode])) {
            foreach ($em->getRepository(KejawenLab\ApiSkeleton\Entity\Permission::class)->findBy(['menu' => $testMenu]) as $permission) {
                $em->remove($permission);
            }
            $em->remove($testMenu);
        }
    }
    $em->flush();
}
