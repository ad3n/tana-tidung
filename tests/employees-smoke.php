<?php

declare(strict_types=1);
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Entity\User;
use KejawenLab\ApiSkeleton\Kernel;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use KejawenLab\Application\Entity\Level;
use Symfony\Component\Dotenv\Dotenv;

// Uses the development fixture account and removes records created by this check.
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
    request('/api/employees', 401);
    $admin = moduleRequest('me', 200);
    require dirname(__DIR__).'/vendor/autoload.php';
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    $em = $kernel->getContainer()->get('doctrine')->getManager();
    $groupFixture = new Group();
    $groupFixture->setCode('E'.substr($suffix, 0, 6));
    $groupFixture->setName('Employee test group');
    $em->persist($groupFixture);
    $em->flush();
    $levelFixture = new Level();
    $levelFixture->setName('Employee test '.$suffix);
    $em->persist($levelFixture);
    $em->flush();
    $level = ['id' => $levelFixture->getId()];
    $organization = moduleRequest('organizations', 201, 'POST', ['name' => 'Employee test '.$suffix, 'code' => 'O'.$suffix]);
    $created[] = 'organizations/'.$organization['id'];
    $data = ['group' => $groupFixture->getId(), 'username' => 'employee'.$suffix, 'email' => 'employee'.$suffix.'@example.test', 'plainPassword' => 'EmployeeTest123!', 'organization' => $organization['id'], 'level' => $level['id'], 'code' => 'E'.$suffix, 'name' => 'Pegawai '.$suffix];
    foreach (array_keys($data) as $field) {
        $invalid = $data;
        unset($invalid[$field]);
        moduleRequest('employees', 400, 'POST', $invalid);
    }
    foreach (['organization', 'level'] as $field) {
        $invalid = $data;
        $invalid[$field] = '00000000-0000-4000-8000-000000000000';
        moduleRequest('employees', 400, 'POST', $invalid);
    }
    $invalid = $data;
    $invalid['group'] = $admin['group']['id'];
    moduleRequest('employees', 400, 'POST', $invalid);
    $employee = moduleRequest('employees', 201, 'POST', $data);
    $userFixture = $em->getRepository(User::class)->find($employee['user']['id']);
    checkModule(password_verify('EmployeeTest123!', $userFixture->getPassword()), 'Password not hashed correctly');
    checkModule($employee['user']['group']['id'] === $data['group'], 'Group not assigned');
    checkModule($employee['user']['email'] === $data['email'], 'Email not assigned');
    checkModule($userFixture->getFullName() === StringUtil::title($data['name']), 'User name not synchronized on create');
    $originalHash = $userFixture->getPassword();
    $path = 'employees/'.$employee['id'];
    $created[] = $path;
    foreach (['organization', 'level'] as $field) {
        checkModule($employee[$field]['id'] === $data[$field], 'Incorrect relation: '.$field);
    }
    checkModule(!isset($employee['user']['password']), 'Password exposed');
    moduleRequest('employees', 400, 'POST', $data);
    $duplicate = $data;
    $duplicate['code'] = 'D'.$suffix;
    $duplicate['email'] = 'different'.$suffix.'@example.test';
    moduleRequest('employees', 400, 'POST', $duplicate);
    $duplicate['username'] = 'different'.$suffix;
    $duplicate['email'] = $data['email'];
    moduleRequest('employees', 400, 'POST', $duplicate);
    moduleRequest($path, 200);
    moduleRequest($path.'/audit', 200);
    $data['name'] = 'Updated '.$suffix;
    $data['plainPassword'] = '';
    $data['email'] = 'updated'.$suffix.'@example.test';
    checkModule(moduleRequest($path, 200, 'PUT', $data)['name'] === $data['name'], 'Update failed');
    $em->refresh($userFixture);
    checkModule($userFixture->getPassword() === $originalHash, 'Empty password changed existing hash');
    checkModule($userFixture->getEmail() === $data['email'], 'User update not persisted');
    checkModule($userFixture->getFullName() === StringUtil::title($data['name']), 'User name not synchronized on edit');
    $data['plainPassword'] = 'ChangedPassword123!';
    moduleRequest($path, 200, 'PUT', $data);
    $em->refresh($userFixture);
    checkModule(password_verify($data['plainPassword'], $userFixture->getPassword()), 'Password update failed');
    checkModule(1 === moduleRequest('employees?q=E'.$suffix, 200)['total_item'], 'Code search failed');
    checkModule(1 === moduleRequest('employees?q='.rawurlencode($data['name']), 200)['total_item'], 'Name search failed');
    checkModule(0 === moduleRequest('employees?q=missing'.$suffix, 200)['total_item'], 'Search filtering failed');
} finally {
    foreach (array_reverse($created) as $path) {
        moduleRequest($path, 204, 'DELETE');
        moduleRequest($path, 404);
    }
    if (isset($levelFixture)) {
        $em->remove($levelFixture);
        if (isset($userFixture)) {
            $em->remove($userFixture);
        }
        $em->remove($groupFixture);
        $em->flush();
        $kernel->shutdown();
    }
}
[, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
$cookies = [];
foreach ($headers as $header) {
    if (0 === stripos($header, 'Set-Cookie: ')) {
        $cookies[] = explode(';', substr($header, 12))[0];
    }
}
[$html] = request('/admin/employees', 200, headers: ['Cookie: '.implode('; ', $cookies)]);
checkModule(str_contains($html, 'Daftar Pegawai'), 'Missing translated page title');
checkModule(!str_contains($html, 'sas.form.field.employee.'), 'Missing form translations');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$groupSelect = $dom->getElementById('employee_group');
checkModule(null !== $groupSelect, 'Missing group selection');
checkModule(null === $dom->getElementById('employee_fullName'), 'Redundant user name field still displayed');
foreach ($groupSelect->getElementsByTagName('option') as $option) {
    checkModule($option->getAttribute('value') !== $admin['group']['id'], 'Superadmin selectable');
}
checkModule(strpos($html, 'id="employee_group"') < strpos($html, 'id="employee_username"'), 'Incorrect field order');
echo "Employee checks passed.\n";
