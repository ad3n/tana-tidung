<?php

declare(strict_types=1);
use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Kernel;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Entity\ItemUnitConversion;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Unit\Validator\UnitConfiguration;
use KejawenLab\Application\Unit\Validator\UnitConfigurationValidator;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;

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

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$suffix = strtoupper(bin2hex(random_bytes(5)));
$units = [];
$item = $category = null;
try {
    [$login] = request('/api/login', 200, 'POST', json_encode(['username' => 'admin', 'password' => 'admin']), ['Content-Type: application/json']);
    $token = json_decode($login, true)['token'];
    foreach (['mass', 'volume', 'package', 'count'] as $type) {
        $units[$type] = moduleRequest('units', 201, 'POST', ['code' => $type.$suffix, 'name' => $type.$suffix, 'type' => $type]);
    }
    moduleRequest('units', 400, 'POST', ['code' => 'BAD'.$suffix, 'name' => 'Invalid', 'type' => 'invalid']);
    $category = moduleRequest('categories', 201, 'POST', ['name' => 'Conversion '.$suffix]);
    $itemData = ['category' => $category['id'], 'unit' => $units['mass']['id'], 'code' => 'CV'.$suffix, 'name' => 'Conversion '.$suffix];
    moduleRequest('items', 400, 'POST', array_diff_key($itemData, ['unit' => true]));
    $item = moduleRequest('items', 201, 'POST', $itemData);
    moduleRequest('units/'.$units['mass']['id'], 409, 'DELETE');
    $path = '/admin/items/'.$item['id'].'/conversions';
    request($path, 302);
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => 'admin', '_password' => 'admin']), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $m)) {
            $cookies[] = $m[1];
        }
    }
    $auth = ['Cookie: '.implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    [$detail] = request('/admin/items/'.$item['id'], 200, headers: $auth);
    checkModule(str_contains($detail, $path), 'Conversion action missing from detail');
    [$html] = request($path, 200, headers: $auth);
    checkModule(!str_contains($html, 'value="'.$units['volume']['id'].'"'), 'Volume selectable for mass');
    foreach ([['volume', '1'], ['mass', '1'], ['package', '0'], ['package', '-1'], ['package', '0.0000001']] as [$type, $factor]) {
        request($path, 200, 'POST', http_build_query(['item_unit_conversion' => ['unit' => $units[$type]['id'], 'factor' => $factor]]), $auth);
        checkModule(0 === (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM item_unit_conversions WHERE item_id = ?', [$item['id']]), 'Invalid conversion persisted');
    }
    $payload = http_build_query(['item_unit_conversion' => ['unit' => $units['package']['id'], 'factor' => '25.125']]);
    request($path, 303, 'POST', $payload, $auth);
    request($path, 200, 'POST', $payload, $auth);
    [$html] = request($path, 200, headers: $auth);
    checkModule(str_contains($html, '25,13') && !str_contains($html, 'value="25.125"'), 'Stored factor or form reset incorrect');
    checkModule(1 === (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM item_unit_conversions WHERE item_id = ?', [$item['id']]), 'Duplicate conversion persisted');

    // Validate cross-dimension rejection independently of the filtered HTML choices.
    $entity = $em->find(Item::class, $item['id']);
    $validator = Validation::createValidatorBuilder()->setConstraintValidatorFactory(
        new class($em) implements ConstraintValidatorFactoryInterface {
            public function __construct(private EntityManagerInterface $em)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return new UnitConfigurationValidator($this->em);
            }
        }
    )->getValidator();
    $constraint = new UnitConfiguration();
    $conversion = new ItemUnitConversion();
    $conversion->setItem($entity);
    $conversion->setUnit($em->find(Unit::class, $units['volume']['id']));
    checkModule(count($validator->validate($conversion, $constraint)) > 0, 'Mass to volume accepted by validator');
    $entity->setUnit($em->find(Unit::class, $units['count']['id']));
    checkModule(count($validator->validate($entity, $constraint)) > 0, 'Base unit changed despite conversions');
    $mass = $em->find(Unit::class, $units['mass']['id']);
    $mass->setType('volume');
    checkModule(count($validator->validate($mass, $constraint)) > 0, 'Used unit type change accepted');
    $conversionId = $em->getConnection()->fetchOne('SELECT id FROM item_unit_conversions WHERE item_id = ?', [$item['id']]);
    request($path.'/'.$conversionId.'/delete', 303, 'POST', '', $auth);
    checkModule(0 === (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM item_unit_conversions WHERE item_id = ?', [$item['id']]), 'Delete failed');
    $em->getConnection()->executeStatement('UPDATE units SET deleted_at = NOW() WHERE id = ?', [$units['package']['id']]);
    $em->getConnection()->executeStatement('UPDATE units SET type = ? WHERE id = ?', ['unclassified', $units['mass']['id']]);
    [$html] = request($path, 200, headers: $auth);
    checkModule(str_contains($html, 'Belum ada satuan yang sesuai untuk konversi.'), 'Empty choice explanation missing');
    checkModule(!str_contains($html, 'name="item_unit_conversion[unit]"'), 'Empty conversion selector still shown');
    echo "Item unit conversion checks passed.\n";
} finally {
    $db = $em->getConnection();
    if ($item) {
        $db->delete('item_unit_conversions', ['item_id' => $item['id']]);
        $db->delete('items', ['id' => $item['id']]);
    }
    if ($category) {
        $db->delete('categories', ['id' => $category['id']]);
    }
    foreach ($units as $unit) {
        $db->delete('units', ['id' => $unit['id']]);
    }
}
