<?php

declare(strict_types=1);
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Entity\Permission;
use KejawenLab\ApiSkeleton\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Run against an installation with the bundled development fixtures.
$base = getenv('SMOKE_BASE_URL') ?: 'http://nginx';

function request(string $path, int $expected, string $method = 'GET', string $payload = '', array $headers = []): array
{
    global $base;
    require_once __DIR__.'/admin-csrf.php';
    [$payload, $headers] = prepareAdminCsrf($path, $method, $payload, $headers);

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

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

$code = 'T'.strtoupper(bin2hex(random_bytes(2)));
$repo = $em->getRepository(Group::class);
$menu = new Group();
$menu->setCode($code);
$menu->setName('Test menu');
$em->persist($menu);
$em->flush();
try {
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) {
        if (0 === stripos($header, 'Set-Cookie: ')) {
            $cookies[] = explode(';', substr($header, 12))[0];
        }
    }
    $headers = ['Cookie: '.implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    request('/admin/levels', 200, headers: $headers);
    request('/admin/groups/'.$menu->getId().'/edit', 302, headers: $headers);
    request('/admin/groups', 200, headers: $headers);
    $data = ['code' => $code, 'name' => 'Updated test'];
    [$invalidHtml] = request('/admin/groups', 200, 'POST', http_build_query(['group' => ['code' => $code, 'name' => '']]), $headers);
    if (!str_contains($invalidHtml, 'value="'.$code.'"')) {
        throw new RuntimeException('Invalid form lost input');
    }
    request('/admin/groups', 303, 'POST', http_build_query(['group' => $data]), $headers);
    [$html] = request('/admin/groups', 200, headers: $headers);
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    foreach (['group_code', 'group_name'] as $field) {
        if ('' !== $doc->getElementById($field)->getAttribute('value')) {
            throw new RuntimeException('Saved form was not reset: '.$field);
        }
    }

    $data['code'] = $code.'N';
    $data['name'] = 'New test';
    request('/admin/groups', 303, 'POST', http_build_query(['group' => $data]), $headers);
    [$html] = request('/admin/groups', 200, headers: $headers);
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    foreach (['group_code', 'group_name'] as $field) {
        if ('' !== $doc->getElementById($field)->getAttribute('value')) {
            throw new RuntimeException('Saved form was not reset: '.$field);
        }
    }

    $em->clear();
    if (!$repo->findOneBy(['code' => $code]) || !$repo->findOneBy(['code' => $code.'N'])) {
        throw new RuntimeException('Adding after editing overwrote the previous menu.');
    }
    echo "Group edit then add regression passed.\n";
} finally {
    $em->clear();
    foreach ([$code, $code.'N'] as $testCode) {
        if ($testMenu = $repo->findOneBy(['code' => $testCode])) {
            foreach ($em->getRepository(Permission::class)->findBy(['group' => $testMenu]) as $permission) {
                $em->remove($permission);
            }
            $em->remove($testMenu);
        }
    }
    $em->flush();
}
