<?php

declare(strict_types=1);
use Brick\Math\BigDecimal;
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Entity\Menu;
use KejawenLab\ApiSkeleton\Entity\Permission;
use KejawenLab\ApiSkeleton\Entity\User;
use KejawenLab\ApiSkeleton\Kernel;
use KejawenLab\Application\Entity\Category;
use KejawenLab\Application\Entity\Employee;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Entity\ItemUnitConversion;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Entity\Unit;
use Symfony\Component\Dotenv\Dotenv;

// Uses isolated test accounts and removes records created by this check.
$base = getenv('SMOKE_BASE_URL') ?: 'http://nginx';

function request(string $path, int $expected, string $method = 'GET', string $payload = '', array $headers = [], bool $csrf = true): array
{
    global $base;

    if ($csrf && str_starts_with($path, '/admin') && 'POST' === $method) {
        $login = '/admin/' === $path;
        [$page, $pageHeaders] = request($login ? '/admin/login' : '/admin/me', 200, headers: $headers);
        preg_match('/name="_csrf_token" value="([^"]+)"/', $page, $match);
        checkModule(isset($match[1]), 'Missing CSRF token in form');
        parse_str($payload, $fields);
        $fields['_csrf_token'] = html_entity_decode($match[1]);
        $payload = http_build_query($fields);
        if ($login) {
            foreach ($pageHeaders as $header) {
                if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $cookie)) {
                    $headers[] = 'Cookie: '.$cookie[1];
                }
            }
        }
    }

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
$db = $em->getConnection();
$suffix = strtoupper(bin2hex(random_bytes(3)));
$users = $orgs = $groups = $permissions = $employees = $transactions = [];
$masters = [];
$tokens = [];
$password = 'TransactionTest123!';
function apiAs(string $who, string $path, int $status, string $method = 'GET', array $data = []): array
{
    global $tokens, $token;
    $token = $tokens[$who];

    return moduleRequest($path, $status, $method, $data);
}
function newTransaction(string $who, array $data): array
{
    global $transactions, $orgs, $year;
    static $codes = [];
    $data += ['reference_number' => 'REF-'.bin2hex(random_bytes(6))];
    $transaction = apiAs($who, 'transactions', 201, 'POST', $data);
    $transactions[] = $transaction['id'];
    $ownerId = 1 === $transaction['type'] ? $transaction['to_organization_id'] : $transaction['from_organization_id'];
    $owner = array_values(array_filter($orgs, static fn ($org) => $org->getId() === $ownerId))[0];
    $prefix = 'TRX'.$year->getYear().$owner->getCode().str_replace('-', '', $data['date']);
    checkModule((bool) preg_match('/^'.preg_quote($prefix, '/').'[1-9][0-9]{4}$/D', $transaction['code']), 'Incorrect transaction number format');
    checkModule(!in_array($transaction['code'], $codes, true), 'Duplicate transaction number');
    $codes[] = $transaction['code'];

    return $transaction;
}
function stockQty(string $who, string $org, string $item, string $location = 'AVAILABLE'): string
{
    $qty = BigDecimal::of(0);
    foreach (apiAs($who, 'transactions/stocks', 200)['items'] as $stock) {
        if ($stock['organization_id'] === $org && $stock['item_id'] === $item && $stock['location'] === $location) {
            $qty = $qty->plus($stock['qty']);
        }
    }

    return (string) $qty->toScale(6);
}
try {
    $year = $em->getRepository(FiscalYear::class)->findOneBy(['status' => true]);
    if (!$year) {
        throw new RuntimeException('An active fiscal year is required');
    }
    $date = (int) date('Y') === $year->getYear() ? date('Y-m-d') : $year->getYear().'-01-01';
    $menu = $em->getRepository(Menu::class)->findOneBy(['code' => 'TRANSACTION']);
    $super = $em->getRepository(Group::class)->findOneBy(['code' => $_ENV['APP_SUPER_ADMIN']]);
    foreach (['full', 'read', 'none'] as $i => $kind) {
        $group = new Group();
        $group->setCode(substr($kind, 0, 1).$suffix);
        $group->setName('Tx '.$kind.$suffix);
        $em->persist($group);
        $em->flush();
        $groups[$kind] = $group;
        $permission = new Permission();
        $permission->setGroup($group);
        $permission->setMenu($menu);
        $permission->setViewable('none' !== $kind);
        $permission->setAddable('full' === $kind);
        $permission->setEditable('full' === $kind);
        $permission->setDeletable('full' === $kind);
        $em->persist($permission);
        $em->flush();
        $permissions[] = $permission;
    }
    foreach ($em->getRepository(Menu::class)->findAll() as $auditMenu) {
        if ('TRANSACTION' === $auditMenu->getCode()) {
            continue;
        }
        foreach (['read', 'full'] as $kind) {
            $permission = new Permission();
            $permission->setGroup($groups[$kind]);
            $permission->setMenu($auditMenu);
            $permission->setViewable(true);
            $permission->setAddable('full' === $kind);
            $permission->setEditable('full' === $kind);
            $permission->setDeletable('full' === $kind);
            $em->persist($permission);
            $permissions[] = $permission;
        }
    }
    $em->flush();
    $level = new Level();
    $level->setName('Tx test '.$suffix);
    $em->persist($level);
    $em->flush();
    $masters['levels'] = $level->getId();
    foreach (['A', 'B', 'C'] as $key) {
        $org = new Organization();
        $org->setCode(str_pad('TX'.$key.$suffix, 27, '0'));
        $org->setName('Tx '.$key.$suffix);
        $em->persist($org);
        $em->flush();
        $orgs[$key] = $org;
    }
    foreach (['admin', 'A', 'B', 'C', 'orphan', 'reader', 'denied', 'apprA', 'apprB'] as $key) {
        $user = new User();
        $user->setUsername('tx'.$key.strtolower($suffix));
        $user->setFullName('Tx '.$key);
        $user->setEmail('tx'.$key.$suffix.'@example.test');
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setGroup('admin' === $key ? $super : $groups[match ($key) {
            'reader' => 'read', 'denied' => 'none', default => 'full',
        }]);
        if (in_array($key, ['A', 'B', 'C'], true)) {
            $user->setGroup($em->getRepository(Group::class)->findOneBy(['code' => 'OPRTR']));
        }

        if (str_starts_with($key, 'appr')) {
            $user->setGroup($em->getRepository(Group::class)->findOneBy(['code' => 'APPRV']));
        }
        $em->persist($user);
        $em->flush();
        $users[$key] = $user;
        if (!in_array($key, ['admin', 'orphan'], true)) {
            $employee = new Employee();
            $employee->setUser($user);
            $employee->setOrganization($orgs['apprB' === $key ? 'B' : $key] ?? $orgs['A']);
            $employee->setLevel($level);
            $employee->setCode('TX'.$key.$suffix);
            $employee->setName('Tx '.$key);
            $em->persist($employee);
            $em->flush();
            $employees[] = $employee;
        }
        [$login] = request('/api/login', 200, 'POST', json_encode(['username' => $user->getUsername(), 'password' => $password]), ['Content-Type: application/json']);
        $tokens[$key] = json_decode($login, true)['token'];
    }
    apiAs('orphan', 'users', 403, 'POST', ['group' => $super->getId(), 'fullName' => 'Escalation test', 'username' => 'txescalate'.strtolower($suffix), 'email' => 'txescalate'.$suffix.'@example.test', 'plainPassword' => $password]);
    apiAs('orphan', 'groups/'.$groups['full']->getId().'/permissions', 403, 'PUT', ['menu' => $menu->getId(), 'viewable' => false, 'addable' => false, 'editable' => false, 'deletable' => false]);
    foreach (['levels', 'organizations', 'employees', 'units', 'sources', 'categories', 'items', 'fiscal-years', 'users', 'groups', 'menus', 'settings'] as $section) {
        apiAs('reader', $section, 403, 'POST', []);
        apiAs('denied', $section, 403);
    }
    [$warmLevels] = request('/api/levels?q='.urlencode($level->getName()), 200, headers: ['Authorization: Bearer '.$tokens['admin']]);
    [$coldLevels] = request('/api/levels?q=NO_MATCH_'.$suffix, 200, headers: ['Authorization: Bearer '.$tokens['admin']]);
    checkModule(str_contains($warmLevels, $level->getId()) && !str_contains($coldLevels, $level->getId()), 'Pagination cache must isolate different filters');
    $unit = new Unit();
    $unit->setCode('KG'.$suffix);
    $unit->setName('Tx Kg');
    $unit->setType('mass');
    $em->persist($unit);
    $pack = new Unit();
    $pack->setCode('PACK'.$suffix);
    $pack->setName('Tx Karung');
    $pack->setType('package');
    $em->persist($pack);
    $category = new Category();
    $category->setName('Tx category');
    $em->persist($category);
    $source = new Source();
    $source->setName('Tx source');
    $em->persist($source);
    $em->flush();
    $masters['units'] = [$unit->getId(), $pack->getId()];
    $masters['categories'] = $category->getId();
    $masters['sources'] = $source->getId();
    $item = new Item();
    $item->setCategory($category);
    $item->setCode('TX'.$suffix);
    $item->setName('Tx item');
    $item->setUnit($unit);
    $item->setStatus(true);
    $em->persist($item);
    $em->flush();
    $masters['items'] = $item->getId();
    $conversion = new ItemUnitConversion();
    $conversion->setItem($item);
    $conversion->setUnit($pack);
    $conversion->setFactor('25');
    $em->persist($conversion);
    $em->flush();
    $masters['item_unit_conversions'] = $conversion->getId();
    request('/api/transactions', 401);
    apiAs('orphan', 'transactions', 403);
    apiAs('denied', 'transactions', 403);
    $lineData = ['item_id' => $item->getId(), 'unit_id' => $pack->getId(), 'qty' => '2', 'price' => '125000.50', 'expiry_date' => date('Y-m-d', strtotime(max($date, date('Y-m-d')).' +100 days')), 'batch_number' => 'LOT-'.$suffix];
    $receiptData = ['type' => 1, 'date' => $date, 'source_id' => $source->getId(), 'reference_number' => 'INV-'.$suffix, 'items' => [$lineData]];
    apiAs('reader', 'transactions', 403, 'POST', $receiptData);
    apiAs('apprA', 'transactions', 403, 'POST', $receiptData);
    apiAs('A', 'transactions', 403, 'POST', $receiptData + ['to_organization_id' => $orgs['B']->getId()]);
    apiAs('A', 'transactions', 400, 'POST', $receiptData + ['status' => 3]);
    $before = (int) $db->fetchOne('SELECT COUNT(*) FROM transactions');
    foreach ([[], [array_replace($lineData, ['qty' => '-1'])], [$lineData, array_replace($lineData, ['qty' => '0'])]] as $invalidLines) {
        apiAs('A', 'transactions', 400, 'POST', array_replace($receiptData, ['items' => $invalidLines]));
        checkModule($before === (int) $db->fetchOne('SELECT COUNT(*) FROM transactions'), 'Invalid submission left partial transaction');
    }
    foreach (['', '   ', '-1', '1.001', '1000000000000000000.00', 'abc'] as $invalidPrice) {
        apiAs('A', 'transactions', 400, 'POST', array_replace($receiptData, ['items' => [array_replace($lineData, ['price' => $invalidPrice])]]));
    }
    apiAs('A', 'transactions', 400, 'POST', array_replace($receiptData, ['reference_number' => '']));
    $receipt = newTransaction('A', $receiptData);
    apiAs('A', 'transactions', 400, 'POST', array_replace($receiptData, ['items' => [array_diff_key($lineData, ['price' => true])]]));
    $zeroPrice = newTransaction('A', array_replace($receiptData, ['items' => [array_replace($lineData, ['price' => '0.00'])]]));
    checkModule('0.00' === $zeroPrice['items'][0]['price'], 'Zero receipt price failed');
    $receiptList = apiAs('A', 'transactions?q='.$receipt['code'], 200);
    checkModule(['value' => '250001.00', 'estimated' => false] === $receiptList['amounts'][$receipt['id']], 'Receipt total incorrect');
    $checkListed = static function (array $transaction, array $visibility): void {
        foreach ($visibility as $actor => $visible) {
            $list = apiAs($actor, 'transactions?q='.$transaction['code'], 200);
            checkModule(($visible ? 1 : 0) === $list['total'], 'Unexpected transaction visibility for '.$actor.' / '.$transaction['code']);
            checkModule($visible === in_array($transaction['id'], array_column($list['items'], 'id'), true), 'List rows differ from visibility count');
        }
    };
    $checkListed($receipt, ['A' => true, 'B' => false, 'apprA' => true, 'apprB' => false, 'admin' => true]);
    $r = 'transactions/'.$receipt['id'];
    checkModule(5 === $receipt['status'] && ['submit'] === array_column($receipt['workflow_history'], 'action'), 'Input did not submit atomically');
    checkModule('125000.50' === $receipt['items'][0]['price'], 'Receipt price not saved exactly');
    checkModule('250001.00' === $receipt['items'][0]['total'], 'Receipt line total must use input quantity and unit price');
    checkModule('50.000000' === $receipt['items'][0]['base_qty'], 'Conversion snapshot incorrect');
    checkModule('0.000000' === stockQty('A', $orgs['A']->getId(), $item->getId()), 'Submission affected stock');
    apiAs('A', $r.'/verify', 403, 'POST');
    apiAs('A', $r.'/approve', 403, 'POST');
    apiAs('apprB', $r.'/verify', 404, 'POST');
    apiAs('A', $r.'/items', 409, 'POST', $lineData);
    apiAs('A', $r, 409, 'PUT', $receiptData);
    apiAs('apprA', $r.'/return', 400, 'POST');
    $returned = apiAs('apprA', $r.'/return', 200, 'POST', ['note' => 'Perbaiki faktur']);
    checkModule(7 === $returned['status'], 'Revision request failed');
    $checkListed($receipt, ['A' => true, 'apprA' => false, 'admin' => true]);
    apiAs('apprA', $r, 403, 'PUT', $receiptData);
    $retained = $returned['items'][0];
    $kept = ['line_id' => $retained['id'], 'item_id' => $retained['item_id'], 'unit_id' => $retained['unit_id'], 'qty' => $retained['qty'], 'price' => $retained['price'], 'batch_number' => $retained['batch_number'], 'expiry_date' => $retained['expiry_date'], 'stock_lot_id' => $retained['stock_lot_id']];
    apiAs('A', $r, 400, 'PUT', array_replace($receiptData, ['reference_number' => 'INVALID', 'items' => [$kept, array_replace($lineData, ['qty' => '0'])]]));
    $unchanged = apiAs('A', $r, 200);
    checkModule(7 === $unchanged['status'] && $unchanged['reference_number'] === $receipt['reference_number'] && 1 === count($unchanged['items']), 'Invalid revision was not rolled back');
    $db->update('item_unit_conversions', ['factor' => '30'], ['id' => $conversion->getId()]);
    $revised = apiAs('A', $r, 200, 'PUT', array_replace($receiptData, ['reference_number' => 'REVISED-'.$suffix, 'items' => [$kept]]));
    checkModule(5 === $revised['status'] && '50.000000' === $revised['items'][0]['base_qty'], 'Revision did not resubmit or changed snapshot');
    checkModule('125000.50' === $revised['items'][0]['price'], 'Revision lost receipt price');
    $db->update('item_unit_conversions', ['factor' => '25'], ['id' => $conversion->getId()]);
    $checkListed($receipt, ['A' => true, 'apprA' => true]);
    checkModule(3 === apiAs('apprA', $r.'/verify', 200, 'POST')['status'], 'Receipt approval failed');
    apiAs('apprA', $r.'/verify', 409, 'POST');
    $checkListed($receipt, ['A' => true, 'B' => false, 'apprA' => false, 'admin' => true]);
    checkModule('50.000000' === stockQty('A', $orgs['A']->getId(), $item->getId()), 'Receipt stock incorrect');
    $second = newTransaction('A', array_replace($receiptData, ['items' => [array_replace($lineData, ['unit_id' => $unit->getId(), 'qty' => '10', 'price' => '25000.25', 'batch_number' => '', 'expiry_date' => date('Y-m-d', strtotime($lineData['expiry_date'].' +10 days'))])]]));
    $second = apiAs('admin', 'transactions/'.$second['id'].'/verify', 200, 'POST');
    checkModule(str_starts_with($second['items'][0]['batch_number'], 'BATCH'), 'Automatic batch missing');
    $transferData = ['type' => 2, 'date' => $date, 'to_organization_id' => $orgs['B']->getId(), 'reference_number' => 'SK-'.$suffix, 'items' => [['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '55']]];
    apiAs('A', 'transactions', 409, 'POST', array_replace($transferData, ['items' => [array_replace($transferData['items'][0], ['qty' => '61'])]]));
    apiAs('A', 'transactions', 400, 'POST', array_replace($transferData, ['items' => [array_replace($transferData['items'][0], ['price' => '1.00'])]]));
    $transfer = newTransaction('A', $transferData);
    $checkListed($transfer, ['A' => true, 'B' => true, 'C' => false, 'apprA' => true, 'apprB' => false, 'admin' => true]);
    $transferList = apiAs('A', 'transactions?q='.$transfer['code'], 200);
    checkModule(null === $transferList['amounts'][$transfer['id']]['value'], 'Unallocated transfer amount must be unknown');
    $t = 'transactions/'.$transfer['id'];
    apiAs('A', $t.'/approve', 403, 'POST');
    apiAs('A', $t.'/send', 409, 'POST');
    apiAs('apprB', $t.'/approve', 403, 'POST');
    $competingTransfer = newTransaction('A', array_replace($transferData, ['items' => [array_replace($transferData['items'][0], ['qty' => '10'])]]));
    $competingDisposal = newTransaction('A', ['type' => 3, 'date' => $date, 'notes' => 'Rusak', 'items' => [array_replace($transferData['items'][0], ['qty' => '6'])]]);
    $approvedTransfer = apiAs('apprA', $t.'/approve', 200, 'POST');
    $checkListed($transfer, ['A' => true, 'B' => true, 'apprA' => false, 'apprB' => false, 'admin' => true]);
    checkModule('5.000000' === stockQty('A', $orgs['A']->getId(), $item->getId()), 'Approval did not reduce available stock');
    checkModule('55.000000' === stockQty('A', $orgs['A']->getId(), $item->getId(), 'RESERVED'), 'Approval did not reserve stock');
    $reservedSummary = apiAs('A', 'transactions/stocks?summary=1', 200)['items'][0];
    checkModule('55.000000' === $reservedSummary['reserved'] && '60.000000' === $reservedSummary['total'], 'Reserved stock summary incorrect');
    apiAs('apprA', $t.'/approve', 409, 'POST');
    apiAs('apprA', 'transactions/'.$competingTransfer['id'].'/approve', 409, 'POST');
    apiAs('apprA', 'transactions/'.$competingDisposal['id'].'/approve', 409, 'POST');
    checkModule('55.000000' === stockQty('A', $orgs['A']->getId(), $item->getId(), 'RESERVED'), 'Competing disposal consumed reserved stock');
    apiAs('A', 'transactions', 409, 'POST', array_replace($transferData, ['items' => [array_replace($transferData['items'][0], ['qty' => '6'])]]));
    apiAs('apprA', $t.'/send', 403, 'POST');
    $sent = apiAs('A', $t.'/send', 200, 'POST');
    $checkListed($transfer, ['A' => true, 'B' => true, 'apprA' => false, 'apprB' => false]);
    $sentList = apiAs('A', 'transactions?q='.$transfer['code'], 200);
    checkModule('375002.25' === $sentList['amounts'][$transfer['id']]['value'], 'Transfer amount must combine batch prices using receipt conversion factors');
    checkModule($approvedTransfer['items'][0]['allocations'] === $sent['items'][0]['allocations'], 'Send changed reserved batch allocations');
    checkModule('0.000000' === stockQty('A', $orgs['A']->getId(), $item->getId(), 'RESERVED'), 'Send did not consume reservation');
    apiAs('A', $t.'/send', 409, 'POST');
    checkModule(2 === count($sent['items'][0]['allocations']), 'FEFO did not span two batches');
    checkModule('5.000000' === stockQty('A', $orgs['A']->getId(), $item->getId()), 'FEFO available balance incorrect');
    checkModule('55.000000' === stockQty('A', $orgs['A']->getId(), $item->getId(), 'IN_TRANSIT'), 'Transit balance incorrect');
    apiAs('A', $t.'/receive', 403, 'POST');
    apiAs('B', $t.'/receive', 200, 'POST');
    $checkListed($transfer, ['A' => true, 'B' => true, 'apprA' => false, 'apprB' => false, 'admin' => true]);
    checkModule('55.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Destination stock incorrect');
    $disposalData = ['type' => 3, 'date' => $date, 'reference_number' => 'OUT-'.$suffix, 'notes' => 'Rusak', 'items' => [['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '5']]];
    apiAs('B', 'transactions', 400, 'POST', array_replace($disposalData, ['notes' => '']));
    $disposal = newTransaction('B', $disposalData);
    $checkListed($disposal, ['A' => false, 'B' => true, 'apprA' => false, 'apprB' => true, 'admin' => true]);
    $d = 'transactions/'.$disposal['id'];
    apiAs('B', $d.'/approve', 403, 'POST');
    checkModule(3 === apiAs('apprB', $d.'/approve', 200, 'POST')['status'], 'Disposal approval did not complete immediately');
    $checkListed($disposal, ['B' => true, 'apprB' => false, 'admin' => true]);
    $disposalList = apiAs('B', 'transactions?q='.$disposal['code'], 200);
    checkModule('25000.10' === $disposalList['amounts'][$disposal['id']]['value'], 'Disposal after transfer must retain original receipt batch price');
    apiAs('B', $d.'/post', 403, 'POST');
    apiAs('apprB', $d.'/approve', 409, 'POST');
    checkModule('50.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Disposal balance incorrect');
    $pendingOne = newTransaction('B', array_replace($disposalData, ['items' => [array_replace($disposalData['items'][0], ['qty' => '30'])]]));
    $pendingTwo = newTransaction('B', array_replace($disposalData, ['items' => [array_replace($disposalData['items'][0], ['qty' => '30'])]]));
    apiAs('apprB', 'transactions/'.$pendingOne['id'].'/approve', 200, 'POST');
    apiAs('apprB', 'transactions/'.$pendingTwo['id'].'/approve', 409, 'POST');
    checkModule(5 === apiAs('B', 'transactions/'.$pendingTwo['id'], 200)['status'], 'Insufficient stock changed submitted status');
    $insufficientList = apiAs('B', 'transactions?q='.$pendingTwo['code'], 200);
    checkModule(null === $insufficientList['amounts'][$pendingTwo['id']]['value'], 'Unallocated disposal amount must be unknown');
    checkModule('20.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Failed approval did not roll back stock');
    $cancelTransfer = newTransaction('B', array_replace($transferData, ['to_organization_id' => $orgs['A']->getId(), 'items' => [array_replace($transferData['items'][0], ['qty' => '10'])]]));
    $cancelPath = 'transactions/'.$cancelTransfer['id'];
    apiAs('apprB', $cancelPath.'/approve', 200, 'POST');
    checkModule('10.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Cancel scenario reservation missing');
    apiAs('B', $cancelPath.'/cancel', 403, 'POST', ['note' => 'Not allowed']);
    apiAs('admin', $cancelPath.'/cancel', 200, 'POST', ['note' => 'Transfer dibatalkan']);
    $checkListed($cancelTransfer, ['A' => true, 'B' => true, 'apprA' => false, 'apprB' => false, 'admin' => true]);
    checkModule('20.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Cancellation did not restore available stock');
    checkModule('0.000000' === stockQty('B', $orgs['B']->getId(), $item->getId(), 'RESERVED'), 'Cancellation left reserved stock');
    apiAs('admin', $cancelPath.'/cancel', 409, 'POST', ['note' => 'Repeat']);
    apiAs('B', $cancelPath.'/send', 409, 'POST');
    $expiryTransfer = newTransaction('B', array_replace($transferData, ['to_organization_id' => $orgs['A']->getId(), 'items' => [array_replace($transferData['items'][0], ['qty' => '2'])]]));
    $expiryPath = 'transactions/'.$expiryTransfer['id'];
    $expiryApproved = apiAs('apprB', $expiryPath.'/approve', 200, 'POST');
    $expiryLot = $expiryApproved['items'][0]['allocations'][0]['stock_lot_id'];
    $previousExpiry = $db->fetchOne('SELECT expiry_date FROM stock_lots WHERE id=?', [$expiryLot]);
    $db->update('stock_lots', ['expiry_date' => date('Y-m-d', strtotime('-1 day'))], ['id' => $expiryLot]);
    apiAs('B', $expiryPath.'/send', 409, 'POST');
    checkModule('2.000000' === stockQty('B', $orgs['B']->getId(), $item->getId(), 'RESERVED'), 'Rejected send lost reservation');
    apiAs('admin', $expiryPath.'/cancel', 200, 'POST', ['note' => 'Batch kedaluwarsa']);
    checkModule('20.000000' === stockQty('B', $orgs['B']->getId(), $item->getId()), 'Expired reservation could not be released');
    $db->update('stock_lots', ['expiry_date' => $previousExpiry], ['id' => $expiryLot]);

    $outgoingData = array_replace($transferData, ['to_organization_id' => $orgs['A']->getId(), 'items' => [array_replace($transferData['items'][0], ['qty' => '1'])]]);
    $sentOutgoing = newTransaction('B', $outgoingData);
    apiAs('apprB', 'transactions/'.$sentOutgoing['id'].'/approve', 200, 'POST');
    apiAs('B', 'transactions/'.$sentOutgoing['id'].'/send', 200, 'POST');
    $reservedOutgoing = newTransaction('B', $outgoingData);
    apiAs('apprB', 'transactions/'.$reservedOutgoing['id'].'/approve', 200, 'POST');

    $returnedTransfer = newTransaction('B', $outgoingData);
    apiAs('apprB', 'transactions/'.$returnedTransfer['id'].'/return', 200, 'POST', ['note' => 'Perbaiki SK']);

    $returnedReceipt = newTransaction('B', array_replace($receiptData, ['items' => [array_replace($lineData, ['batch_number' => 'RETURNED-'.$suffix])]]));
    apiAs('apprB', 'transactions/'.$returnedReceipt['id'].'/return', 200, 'POST', ['note' => 'Perbaiki faktur']);
    $returnedDisposal = newTransaction('B', array_replace($disposalData, ['items' => [array_replace($disposalData['items'][0], ['qty' => '1'])]]));
    apiAs('apprB', 'transactions/'.$returnedDisposal['id'].'/return', 200, 'POST', ['note' => 'Perbaiki pengeluaran']);

    apiAs('A', 'transactions/stocks?organization_id='.$orgs['B']->getId(), 403);
    $summary = apiAs('A', 'transactions/stocks?summary=1&q='.strtolower($item->getCode()), 200)['items'];
    checkModule(1 === count($summary) && '5.000000' === $summary[0]['total'], 'Stock summary search incorrect');
    $stockPermission = $em->getRepository(Permission::class)->findOneBy(['group' => $groups['read'], 'menu' => $em->getRepository(Menu::class)->findOneBy(['code' => 'TRANSACTION_STOCK'])]);
    $stockPermission->setViewable(false);
    $em->flush();
    apiAs('reader', 'transactions/stocks', 403);
    $stockPermission->setViewable(true);
    $em->flush();
    foreach (['reader', 'orphan', 'admin', 'apprA', 'A', 'B'] as $who) {
        [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => $users[$who]->getUsername(), '_password' => $password]), ['Content-Type: application/x-www-form-urlencoded']);
        $cookies = [];
        foreach ($headers as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $m)) {
                $cookies[] = $m[1];
            }
        }
        $auth = ['Cookie: '.implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
        if (in_array($who, ['reader', 'orphan'], true)) {
            foreach (['levels', 'organizations', 'employees', 'units', 'sources', 'categories', 'items', 'fiscal-years', 'users', 'groups', 'menus', 'settings', 'crons'] as $section) {
                request('/admin/'.$section, 200, headers: $auth);
                if ('reader' === $who) {
                    request('/admin/'.$section, 403, 'POST', '', $auth);
                }
            }
            request('/admin/levels/'.$level->getId().'/delete', 405, headers: $auth);
            request('/admin/levels', 403, 'POST', '', $auth, csrf: false);
            if ('orphan' === $who) {
                request('/admin/groups/'.$groups['read']->getId().'/permissions/'.$permissions[0]->getId(), 302, 'POST', http_build_query(['type' => 'edit', 'value' => 'true']), $auth);
            }
            continue;
        }
        if ('admin' === $who) {
            foreach (['levels', 'organizations', 'employees', 'units', 'sources', 'categories', 'items', 'fiscal-years', 'users', 'groups', 'menus', 'settings', 'crons'] as $section) {
                [$masterHtml] = request('/admin/'.$section, 200, headers: $auth);
                checkModule(str_contains($masterHtml, 'table entity-list-table') && str_contains($masterHtml, 'id="entityFormModal"'), 'Missing standard table/modal: '.$section);
                checkModule(str_contains($masterHtml, 'data-auto-open="0"') && str_contains($masterHtml, 'name="q"'), 'Unexpected modal/search state: '.$section);
                if (getenv('SIBAPER_UI_PREVIEW') && in_array($section, ['groups', 'employees'], true)) {
                    file_put_contents('/tmp/sibaper-'.$section.'-preview.html', $masterHtml);
                }
                $document = new DOMDocument();
                @$document->loadHTML($masterHtml);
                $xpath = new DOMXPath($document);
                $headersCount = $xpath->query('//table[contains(@class,"entity-list-table")]/thead/tr/th')->length;
                foreach ($xpath->query('//table[contains(@class,"entity-list-table")]/tbody/tr') as $row) {
                    checkModule($row->childNodes->length > 0, 'Empty table row');
                    $cells = $xpath->query('./td', $row);
                    checkModule($cells->length === $headersCount || (1 === $cells->length && (int) $cells->item(0)->getAttribute('colspan') === $headersCount), 'Misaligned table columns: '.$section);
                }
            }
            [$searchHtml] = request('/admin/groups?q='.urlencode($groups['full']->getCode()), 200, headers: $auth);
            checkModule(str_contains($searchHtml, $groups['full']->getCode()) && !str_contains($searchHtml, $groups['read']->getCode()), 'Group search regression');
            request('/admin/groups/'.$groups['full']->getId().'/edit?q=test', 302, headers: $auth);
            [$editHtml] = request('/admin/groups?q=test', 200, headers: $auth);
            checkModule(str_contains($editHtml, 'data-auto-open="1"') && str_contains($editHtml, 'value="'.$groups['full']->getName().'"'), 'Edit must open populated modal');
            $editedName = 'Modal '.ucfirst(strtolower($suffix));
            request('/admin/groups?q=test', 303, 'POST', http_build_query(['group' => ['code' => $groups['full']->getCode(), 'name' => $editedName]]), $auth);
            checkModule($editedName === $db->fetchOne('SELECT name FROM core_group WHERE id=?', [$groups['full']->getId()]), 'Modal save did not update group');
            [$resetHtml] = request('/admin/groups?q=test', 200, headers: $auth);
            checkModule(str_contains($resetHtml, 'data-auto-open="0"') && !str_contains($resetHtml, 'value="'.$groups['full']->getName().'"'), 'Cancel must clear edit form');
            request('/admin/groups/'.$groups['full']->getId().'/edit?q=test', 302, headers: $auth);
            request('/admin/groups?q=test', 200, headers: $auth);
            [$cancelledHtml] = request('/admin/groups?q=test', 200, headers: $auth);
            checkModule(str_contains($cancelledHtml, 'data-auto-open="0"'), 'Cancel must close edit mode');
            [$pageHtml] = request('/admin/groups?page=2', 200, headers: $auth);
            checkModule(str_contains($pageHtml, 'table entity-list-table'), 'Pagination failed');
            [$invalidHtml] = request('/admin/groups', 200, 'POST', http_build_query(['group' => ['code' => '', 'name' => '']]), $auth);
            checkModule(str_contains($invalidHtml, 'data-auto-open="1"') && str_contains($invalidHtml, 'invalid-feedback'), 'Validation errors must reopen modal');
            request('/admin/groups', 200, headers: $auth);
        }
        foreach (['receipts', 'transfers', 'disposals'] as $section) {
            [$html] = request('/admin/transactions/'.$section, 'apprA' === $who ? 403 : 200, headers: $auth);
            if ('apprA' !== $who) {
                checkModule(str_contains($html, 'id="line-price"') === ('receipts' === $section), 'Price input must only appear for receipts');
                checkModule(str_contains($html, '>Ajukan</button>') && !str_contains($html, '>Simpan</button>'), 'Input button not Ajukan');
            }
            [$listHtml] = request('/admin/transactions/'.$section.'/list', 200, headers: $auth);
            checkModule(str_contains($listHtml, '>Jumlah</th>'), 'All transaction lists must show Jumlah');
            checkModule(str_contains($listHtml, 'id="transaction-list-organization"') === ('admin' === $who), 'List filter visibility incorrect');
            if ('apprA' === $who) {
                checkModule(!preg_match('~href="/admin/transactions/(receipts|transfers|disposals|stocks)"~', $listHtml), 'Approver input or stock menu visible');
            }
            if ('admin' === $who) {
                $target = match ($section) {
                    'receipts' => $receipt, 'transfers' => $transfer, 'disposals' => $disposal,
                };
                [$filtered] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $target['code'], 'organization_id' => $orgs['C']->getId()]), 200, headers: $auth);
                checkModule(!str_contains($filtered, 'href="/admin/transactions/'.$target['id'].'"'), 'Filter leaked transaction');
            }
        }
        foreach ([$transfer, $sentOutgoing, $reservedOutgoing, $cancelTransfer, $competingTransfer, $disposal] as $candidate) {
            [$outgoingHtml] = request('/admin/transactions/disposals/list?q='.$candidate['code'], 200, headers: $auth);
            $origin = $candidate['id'] === $transfer['id'] ? 'A' : 'B';
            $visibleStatus = in_array($candidate['id'], [$transfer['id'], $cancelTransfer['id'], $disposal['id']], true);
            $expected = $visibleStatus && ('admin' === $who || $origin === $who);
            if (in_array($who, ['admin', 'A', 'B', 'apprA'], true)) {
                checkModule($expected === str_contains($outgoingHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Outgoing list status or organization scope incorrect');
                if ($expected) {
                    $label = $candidate['id'] === $disposal['id'] ? 'Pengeluaran Langsung' : 'Transfer Keluar';
                    checkModule(str_contains($outgoingHtml, $label), 'Outgoing receipt type label missing');
                    $expectedAmount = $candidate['id'] === $transfer['id'] ? '375.002,25' : ($candidate['id'] === $disposal['id'] ? '25.000,10' : ($candidate['id'] === $cancelTransfer['id'] ? '50.000,20' : '5.000,02'));
                    checkModule(str_contains($outgoingHtml, 'Rp '.$expectedAmount), 'Outgoing batch amount missing or incorrect');
                }
            }
            if ('admin' === $who) {
                foreach (['A', 'B', 'C'] as $filterKey) {
                    [$filteredHtml] = request('/admin/transactions/disposals/list?'.http_build_query(['q' => $candidate['code'], 'organization_id' => $orgs[$filterKey]->getId()]), 200, headers: $auth);
                    checkModule(($visibleStatus && $origin === $filterKey) === str_contains($filteredHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Superadmin outgoing filter must match origin');
                }
            }
        }

        foreach (['receipts' => [$receipt, $transfer, $sentOutgoing], 'disposals' => [$disposal, $transfer, $sentOutgoing]] as $section => $candidates) {
            foreach ($candidates as $candidate) {
                [$allHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $candidate['code'], 'kind' => 'all']), 200, headers: $auth);
                $link = 'href="/admin/transactions/'.$candidate['id'].'"';
                $visible = str_contains($allHtml, $link);
                foreach (['direct', 'transfer'] as $kind) {
                    [$kindHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $candidate['code'], 'kind' => $kind]), 200, headers: $auth);
                    $isTransfer = in_array($candidate['id'], [$transfer['id'], $sentOutgoing['id']], true);
                    checkModule(($visible && $isTransfer === ('transfer' === $kind)) === str_contains($kindHtml, $link), 'Kind filter returned incorrect rows or bypassed organization/status scope');
                    checkModule(str_contains($kindHtml, 'value="'.$kind.'" selected'), 'Kind selection was not retained');
                }
            }
            request('/admin/transactions/'.$section.'/list?kind=invalid', 400, headers: $auth);
        }
        if ('admin' === $who) {
            foreach (['receipts' => 'B', 'disposals' => 'A'] as $section => $owner) {
                foreach (['A', 'B', 'C'] as $filterKey) {
                    [$kindHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $transfer['code'], 'kind' => 'transfer', 'organization_id' => $orgs[$filterKey]->getId()]), 200, headers: $auth);
                    checkModule(($owner === $filterKey) === str_contains($kindHtml, 'href="/admin/transactions/'.$transfer['id'].'"'), 'Kind and organization filters do not combine');
                }
                [$pageHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['page' => 2, 'q' => $transfer['code'], 'kind' => 'transfer', 'organization_id' => $orgs[$owner]->getId()]), 200, headers: $auth);
                checkModule(str_contains($pageHtml, 'kind=transfer') && str_contains($pageHtml, 'organization_id='.$orgs[$owner]->getId()), 'Pagination dropped filters');
            }
        }

        if (in_array($who, ['admin', 'A', 'B', 'apprA'], true)) {
            foreach ([[$transfer, []], [$cancelTransfer, []], [$sentOutgoing, ['A']], [$reservedOutgoing, ['B']], [$returnedTransfer, ['B']], [$competingTransfer, ['A', 'apprA']]] as [$candidate, $actors]) {
                [$followupHtml] = request('/admin/transactions/transfers/list?q='.$candidate['code'], 200, headers: $auth);
                $expected = 'admin' === $who ? [] !== $actors : in_array($who, $actors, true);
                checkModule($expected === str_contains($followupHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Follow-up queue contains incorrect role/status');
                checkModule(str_contains($followupHtml, 'Tindak Lanjut'), 'Follow-up title missing');
                foreach (['submitted' => $competingTransfer, 'approved' => $reservedOutgoing, 'returned' => $returnedTransfer, 'sent' => $sentOutgoing] as $filterStatus => $statusCandidate) {
                    [$statusHtml] = request('/admin/transactions/transfers/list?'.http_build_query(['q' => $candidate['code'], 'status' => $filterStatus, 'organization_id' => $orgs['C']->getId()]), 200, headers: $auth);
                    $matches = $expected && $candidate['id'] === $statusCandidate['id'] && 'admin' !== $who;
                    checkModule($matches === str_contains($statusHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Follow-up status filter bypassed role or organization');
                    if ('admin' === $who) {
                        [$unfilteredOrgHtml] = request('/admin/transactions/transfers/list?'.http_build_query(['q' => $candidate['code'], 'status' => $filterStatus]), 200, headers: $auth);
                        checkModule(($expected && $candidate['id'] === $statusCandidate['id']) === str_contains($unfilteredOrgHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Superadmin follow-up status filter incorrect');
                    }
                    if ('apprA' !== $who) {
                        checkModule(str_contains($statusHtml, 'value="'.$filterStatus.'" selected'), 'Follow-up status selection lost');
                    } else {
                        checkModule(!str_contains($statusHtml, 'id="transaction-list-status"'), 'Approver must not see redundant follow-up status filter');
                    }
                }

            }
        }

        if (in_array($who, ['admin', 'A', 'B', 'apprA'], true)) {
            foreach (['receipts', 'disposals'] as $section) {
                foreach ([$transfer, $cancelTransfer, $sentOutgoing] as $candidate) {
                    [$allHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $candidate['code'], 'kind' => 'transfer']), 200, headers: $auth);
                    $link = 'href="/admin/transactions/'.$candidate['id'].'"';
                    foreach (['completed', 'cancelled'] as $filterStatus) {
                        [$statusHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['q' => $candidate['code'], 'kind' => 'transfer', 'status' => $filterStatus]), 200, headers: $auth);
                        $matches = 'completed' === $filterStatus ? $candidate['id'] === $transfer['id'] : $candidate['id'] === $cancelTransfer['id'];
                        checkModule((str_contains($allHtml, $link) && $matches) === str_contains($statusHtml, $link), 'Status filter bypassed kind, organization or approval restrictions');
                        checkModule(str_contains($statusHtml, 'value="'.$filterStatus.'" selected'), 'Status selection lost');
                    }
                }
                request('/admin/transactions/'.$section.'/list?status=invalid', 400, headers: $auth);
                [$pageHtml] = request('/admin/transactions/'.$section.'/list?'.http_build_query(['page' => 2, 'q' => $transfer['code'], 'kind' => 'transfer', 'status' => 'completed']), 200, headers: $auth);
                checkModule(str_contains($pageHtml, 'status=completed') && str_contains($pageHtml, 'kind=transfer'), 'Pagination dropped status');
            }
        }

        if (in_array($who, ['admin', 'A', 'B', 'apprA'], true)) {
            foreach ([[$returnedReceipt, ['admin', 'B'], 'returned'], [$returnedDisposal, ['admin', 'B'], 'returned'], [$zeroPrice, ['admin', 'A', 'apprA'], 'submitted'], [$pendingTwo, ['admin', 'B'], 'submitted'], [$receipt, [], 'returned'], [$disposal, [], 'submitted']] as [$candidate, $actors, $filterStatus]) {
                foreach (['all', $filterStatus] as $choice) {
                    [$queueHtml] = request('/admin/transactions/transfers/list?'.http_build_query(['q' => $candidate['code'], 'status' => $choice]), 200, headers: $auth);
                    checkModule(in_array($who, $actors, true) === str_contains($queueHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Combined follow-up queue lost receipt/disposal role or status scope');
                }
                if ('admin' === $who) {
                    [$queueHtml] = request('/admin/transactions/transfers/list?'.http_build_query(['q' => $candidate['code'], 'organization_id' => $orgs['C']->getId()]), 200, headers: $auth);
                    checkModule(!str_contains($queueHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Combined follow-up queue bypassed organization filter');
                }
            }
        }

        foreach (['invalid', 'completed', 'cancelled'] as $invalidStatus) {
            request('/admin/transactions/transfers/list?status='.$invalidStatus, 400, headers: $auth);
        }
        [$followupPage] = request('/admin/transactions/transfers/list?page=2&status=submitted', 200, headers: $auth);
        checkModule(str_contains($followupPage, 'status=submitted'), 'Follow-up pagination lost status');

        foreach (['receipts' => [$zeroPrice, $returnedReceipt], 'disposals' => [$pendingTwo, $returnedDisposal, $sentOutgoing]] as $section => $candidates) {
            foreach ($candidates as $candidate) {
                [$historyHtml] = request('/admin/transactions/'.$section.'/list?q='.$candidate['code'], 200, headers: $auth);
                checkModule(!str_contains($historyHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'History includes unfinished transaction');
                checkModule(!str_contains($historyHtml, '<option value="pending"'), 'Removed status option still visible');
            }
            request('/admin/transactions/'.$section.'/list?status=pending', 400, headers: $auth);
        }
        foreach ([$returnedReceipt, $returnedTransfer, $returnedDisposal, $zeroPrice] as $candidate) {
            [$allHtml] = request('/admin/transactions/transfers/list?q='.$candidate['code'], 200, headers: $auth);
            $link = 'href="/admin/transactions/'.$candidate['id'].'"';
            foreach (['receipt' => 1, 'transfer' => 2, 'disposal' => 3] as $filterKind => $kindType) {
                [$kindHtml] = request('/admin/transactions/transfers/list?'.http_build_query(['q' => $candidate['code'], 'kind' => $filterKind]), 200, headers: $auth);
                checkModule((str_contains($allHtml, $link) && $candidate['type'] === $kindType) === str_contains($kindHtml, $link), 'Follow-up kind filter bypassed role or organization scope');
                checkModule(str_contains($kindHtml, 'value="'.$filterKind.'" selected'), 'Follow-up kind filter selection missing');
            }
        }
        request('/admin/transactions/transfers/list?kind=direct', 400, headers: $auth);

        if ('apprA' === $who) {
            request('/admin/transactions/stocks', 403, headers: $auth);
            apiAs($who, 'transactions/stocks', 403);
            apiAs($who, 'transactions/stocks?summary=1', 403);
            continue;
        }

        if ('admin' === $who) {
            foreach (['' => true, 'A' => false, 'B' => true, 'C' => false] as $organizationKey => $expected) {
                $query = ['q' => $transfer['code']];
                if ('' !== $organizationKey) {
                    $query['organization_id'] = $orgs[$organizationKey]->getId();
                }

                [$incomingHtml] = request('/admin/transactions/receipts/list?'.http_build_query($query), 200, headers: $auth);
                checkModule($expected === str_contains($incomingHtml, 'href="/admin/transactions/'.$transfer['id'].'"'), 'Superadmin receipt filter must match transfer destination');
            }
        }

        if (in_array($who, ['admin', 'A', 'B'], true)) {
            foreach ([$transfer, $cancelTransfer, $competingTransfer, $sentOutgoing, $reservedOutgoing] as $candidate) {
                [$incomingHtml] = request('/admin/transactions/receipts/list?q='.$candidate['code'], 200, headers: $auth);
                $destination = $candidate['id'] === $transfer['id'] ? 'B' : 'A';
                $expected = in_array($candidate['id'], [$transfer['id'], $cancelTransfer['id']], true) && ('admin' === $who || $destination === $who);
                checkModule($expected === str_contains($incomingHtml, 'href="/admin/transactions/'.$candidate['id'].'"'), 'Receipt list exposed an outgoing, pending or cancelled transfer');
                if ($expected) {
                    checkModule(str_contains($incomingHtml, 'Transfer Masuk') && str_contains($incomingHtml, $orgs[$destination === 'B' ? 'A' : 'B']->getName()), 'Incoming transfer label or source missing');
                    checkModule(str_contains($incomingHtml, 'Rp '.($candidate['id'] === $transfer['id'] ? '375.002,25' : '50.000,20')), 'Incoming transfer amount missing');
                }
            }
        }

        [$stockHtml] = request('/admin/transactions/stocks', 200, headers: $auth);
        checkModule(str_contains($stockHtml, 'id="stock-organization"') === ('admin' === $who), 'Stock filter visibility incorrect');
        if ('A' === $who) {
            $webInput = array_replace($receiptData, ['reference_number' => 'WEB-'.$suffix]);
            [, $createdHeaders] = request('/admin/transactions/receipts', 303, 'POST', http_build_query($webInput), $auth);
            foreach ($createdHeaders as $header) {
                if (preg_match('~^Location: /admin/transactions/([a-f0-9-]+)$~i', $header, $match)) {
                    $transactions[] = $match[1];
                    checkModule(5 === (int) $db->fetchOne('SELECT status FROM transactions WHERE id = ?', [$match[1]]), 'Web input did not submit directly');
                    [$detail] = request('/admin/transactions/'.$match[1], 200, headers: $auth);
                    checkModule(!str_contains($detail, 'name="_operation" value="verify"') && !str_contains($detail, 'name="_operation" value="approve"'), 'Operator approval action visible');
                }
            }
        }
    }
    checkModule(0 === (int) $db->fetchOne('SELECT COUNT(*) FROM transactions WHERE status = 1'), 'Draft status still exists');
    echo "Direct submission, revisions, roles, stock, FEFO and admin checks passed.\n";
} finally {
    // Delete only test-owned records; never load fixtures or clear operational stock.
    $db->executeStatement('DELETE FROM core_user WHERE username=?', ['txescalate'.strtolower($suffix)]);
    foreach ($users as $user) {
        $ids = $db->fetchFirstColumn('SELECT id FROM transactions WHERE created_by_id = ?', [$user->getId()]);
        $transactions = array_merge($transactions, $ids);
    }
    $transactions = array_unique($transactions);
    foreach ($transactions as $id) {
        $db->executeStatement('UPDATE transaction_items SET selected_lot_id = NULL WHERE transaction_id = ?', [$id]);
    }
    foreach ($transactions as $id) {
        $db->executeStatement('DELETE FROM stock_movements WHERE allocation_id IN (SELECT a.id FROM transaction_item_allocations a JOIN transaction_items i ON i.id=a.transaction_item_id WHERE i.transaction_id=?)', [$id]);
        $db->executeStatement('DELETE FROM transaction_item_allocations WHERE transaction_item_id IN (SELECT id FROM transaction_items WHERE transaction_id=?)', [$id]);
    }
    foreach ($transactions as $id) {
        $db->executeStatement('DELETE FROM stock_lots WHERE receipt_item_id IN (SELECT id FROM transaction_items WHERE transaction_id=?)', [$id]);
    }
    foreach ($transactions as $id) {
        $db->delete('transaction_items', ['transaction_id' => $id]);
        $db->delete('transactions', ['id' => $id]);
    }
    foreach (['item_unit_conversions', 'items', 'sources', 'categories', 'units'] as $table) {
        foreach ((array) ($masters[$table] ?? []) as $id) {
            $db->delete($table, ['id' => $id]);
        }
    }
    foreach ($employees as $employee) {
        $db->delete('employees', ['id' => $employee->getId()]);
    }
    foreach ($users as $user) {
        $db->delete('core_user', ['id' => $user->getId()]);
    }
    foreach ($permissions as $permission) {
        $db->delete('core_permission', ['id' => $permission->getId()]);
    }
    foreach ($groups as $group) {
        $db->delete('core_permission', ['group_id' => $group->getId()]);
        $db->delete('core_group', ['id' => $group->getId()]);
    }
    foreach ($orgs as $org) {
        $db->delete('organizations', ['id' => $org->getId()]);
    }
    if (isset($masters['levels'])) {
        $db->delete('levels', ['id' => $masters['levels']]);
    }
}
