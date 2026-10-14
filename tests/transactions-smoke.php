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

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new KejawenLab\ApiSkeleton\Kernel('dev', true);
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
    $data += ['reference_number' => 'REF-' . bin2hex(random_bytes(6))];
    $transaction = apiAs($who, 'transactions', 201, 'POST', $data);
    $transactions[] = $transaction['id'];
    $ownerId = $transaction['type'] === 1 ? $transaction['to_organization_id'] : $transaction['from_organization_id'];
    $owner = array_values(array_filter($orgs, static fn ($org) => $org->getId() === $ownerId))[0];
    $prefix = 'TRX' . $year->getYear() . $owner->getCode() . str_replace('-', '', $data['date']);
    checkModule((bool) preg_match('/^' . preg_quote($prefix, '/') . '[1-9][0-9]{4}$/D', $transaction['code']), 'Incorrect transaction number format');
    checkModule(!in_array($transaction['code'], $codes, true), 'Duplicate transaction number');
    $codes[] = $transaction['code'];
    return $transaction;
}
function stockQty(string $who, string $org, string $item, string $location = 'AVAILABLE'): string
{
    $qty = Brick\Math\BigDecimal::of(0);
    foreach (apiAs($who, 'transactions/stocks', 200)['items'] as $stock) {
        if ($stock['organization_id'] === $org && $stock['item_id'] === $item && $stock['location'] === $location) $qty = $qty->plus($stock['qty']);
    }
    return (string) $qty->toScale(6);
}
try {
    $year = $em->getRepository(KejawenLab\Application\Entity\FiscalYear::class)->findOneBy(['status' => true]);
    if (!$year) throw new RuntimeException('An active fiscal year is required');
    $date = (int) date('Y') === $year->getYear() ? date('Y-m-d') : $year->getYear() . '-01-01';
    $menu = $em->getRepository(KejawenLab\ApiSkeleton\Entity\Menu::class)->findOneBy(['code' => 'TRANSACTION']);
    $super = $em->getRepository(KejawenLab\ApiSkeleton\Entity\Group::class)->findOneBy(['code' => $_ENV['APP_SUPER_ADMIN']]);
    foreach (['full', 'read', 'none'] as $i => $kind) {
        $group = new KejawenLab\ApiSkeleton\Entity\Group(); $group->setCode(substr($kind,0,1) . $suffix); $group->setName('Tx ' . $kind . $suffix); $em->persist($group); $em->flush(); $groups[$kind] = $group;
        $permission = new KejawenLab\ApiSkeleton\Entity\Permission(); $permission->setGroup($group); $permission->setMenu($menu);
        $permission->setViewable($kind !== 'none'); $permission->setAddable($kind === 'full'); $permission->setEditable($kind === 'full'); $permission->setDeletable($kind === 'full');
        $em->persist($permission); $em->flush(); $permissions[] = $permission;
    }
    $level = new KejawenLab\Application\Entity\Level(); $level->setName('Tx test ' . $suffix); $em->persist($level); $em->flush(); $masters['levels'] = $level->getId();
    foreach (['A', 'B', 'C'] as $key) {
        $org = new KejawenLab\Application\Entity\Organization(); $org->setCode(str_pad('TX' . $key . $suffix, 27, '0')); $org->setName('Tx ' . $key . $suffix); $em->persist($org); $em->flush(); $orgs[$key] = $org;
    }
    foreach (['admin', 'A', 'B', 'C', 'orphan', 'reader', 'denied'] as $key) {
        $user = new KejawenLab\ApiSkeleton\Entity\User(); $user->setUsername('tx' . $key . strtolower($suffix)); $user->setFullName('Tx ' . $key); $user->setEmail('tx' . $key . $suffix . '@example.test'); $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setGroup($key === 'admin' ? $super : $groups[match($key) {'reader' => 'read', 'denied' => 'none', default => 'full'}]);
        $em->persist($user); $em->flush(); $users[$key] = $user;
        if (!in_array($key, ['admin', 'orphan'], true)) {
            $employee = new KejawenLab\Application\Entity\Employee(); $employee->setUser($user); $employee->setOrganization($orgs[$key] ?? $orgs['A']); $employee->setLevel($level); $employee->setCode('TX' . $key . $suffix); $employee->setName('Tx ' . $key); $em->persist($employee); $em->flush(); $employees[] = $employee;
        }
        [$login] = request('/api/login', 200, 'POST', json_encode(['username' => $user->getUsername(), 'password' => $password]), ['Content-Type: application/json']);
        $tokens[$key] = json_decode($login, true)['token'];
    }
    $unit = new KejawenLab\Application\Entity\Unit(); $unit->setCode('KG' . $suffix); $unit->setName('Tx Kg'); $unit->setType('mass'); $em->persist($unit);
    $pack = new KejawenLab\Application\Entity\Unit(); $pack->setCode('PACK' . $suffix); $pack->setName('Tx Karung'); $pack->setType('package'); $em->persist($pack);
    $category = new KejawenLab\Application\Entity\Category(); $category->setName('Tx category'); $em->persist($category);
    $source = new KejawenLab\Application\Entity\Source(); $source->setName('Tx source'); $em->persist($source); $em->flush();
    $masters['units'] = [$unit->getId(), $pack->getId()]; $masters['categories'] = $category->getId(); $masters['sources'] = $source->getId();
    $item = new KejawenLab\Application\Entity\Item(); $item->setCategory($category); $item->setCode('TX' . $suffix); $item->setName('Tx item'); $item->setUnit($unit); $item->setStatus(true); $em->persist($item); $em->flush(); $masters['items'] = $item->getId();
    $conversion = new KejawenLab\Application\Entity\ItemUnitConversion(); $conversion->setItem($item); $conversion->setUnit($pack); $conversion->setFactor('25'); $em->persist($conversion); $em->flush(); $masters['item_unit_conversions'] = $conversion->getId();
    request('/api/transactions', 401);
    apiAs('orphan', 'transactions', 403); apiAs('denied', 'transactions', 403);
    $receiptData = ['type' => 1, 'date' => $date, 'to_organization_id' => $orgs['A']->getId(), 'source_id' => $source->getId()];
    apiAs('reader', 'transactions', 403, 'POST', $receiptData);
    apiAs('A', 'transactions', 403, 'POST', array_replace($receiptData, ['to_organization_id' => $orgs['B']->getId()]));
    apiAs('admin', 'transactions', 400, 'POST', array_replace($receiptData, ['type' => 9]));
    apiAs('admin', 'transactions', 400, 'POST', $receiptData + ['status' => 3]);
    $receipt = newTransaction('admin', $receiptData);
    $r = 'transactions/' . $receipt['id'];
    apiAs('admin', 'transactions', 400, 'POST', $receiptData);
    apiAs('admin', 'transactions', 400, 'POST', $receiptData + ['reference_number' => '   ']);
    apiAs('admin', $r, 400, 'PUT', $receiptData + ['reference_number' => '']);
    checkModule($receipt['status'] === 1 && $receipt['type'] === 1, 'Enum values must be integers');
    apiAs('admin', $r . '/post', 400, 'POST');
    $lineData = ['item_id' => $item->getId(), 'unit_id' => $pack->getId(), 'qty' => '2', 'expiry_date' => date('Y-m-d', strtotime(max($date, date('Y-m-d')) . ' +100 days')), 'batch_number' => 'LOT-' . $suffix];
    apiAs('admin', $r . '/items', 400, 'POST', array_diff_key($lineData, ['expiry_date' => true]));
    apiAs('admin', $r . '/items', 400, 'POST', array_replace($lineData, ['qty' => '-1']));
    $detail = apiAs('admin', $r . '/items', 201, 'POST', $lineData);
    checkModule($detail['items'][0]['base_qty'] === '50.000000', 'Decimal conversion failed');
    apiAs('admin', 'items/' . $item->getId(), 400, 'PUT', ['category' => $category->getId(), 'code' => $item->getCode(), 'name' => 'Tx item', 'unit' => $pack->getId(), 'status' => true]);
    $db->update('item_unit_conversions', ['factor' => '30'], ['id' => $conversion->getId()]);
    checkModule(stockQty('admin', $orgs['A']->getId(), $item->getId()) === '0.000000', 'Draft changed stock');
    $posted = apiAs('admin', $r . '/post', 200, 'POST');
    checkModule($posted['status'] === 3 && $posted['items'][0]['base_qty'] === '50.000000', 'Posting changed conversion snapshot');
    checkModule($posted['items'][0]['batch_number'] === $lineData['batch_number'] && $posted['items'][0]['allocations'][0]['batch_number'] === $lineData['batch_number'], 'Manual batch number changed');
    apiAs('admin', $r . '/post', 409, 'POST'); apiAs('admin', $r, 409, 'PUT', $receiptData);
    checkModule(stockQty('A', $orgs['A']->getId(), $item->getId()) === '50.000000', 'Receipt did not add stock');
    foreach (['items' => $item->getId(), 'units' => $unit->getId(), 'sources' => $source->getId(), 'organizations' => $orgs['A']->getId()] as $module => $recordId) {
        apiAs('admin', $module . '/' . $recordId, 409, 'DELETE');
    }
    apiAs('admin', 'transactions/stocks?organization_id=invalid', 400);
    apiAs('B', $r, 404); apiAs('A', 'transactions/stocks?organization_id=' . $orgs['B']->getId(), 403);
    $transferData = ['type' => 2, 'date' => $date, 'to_organization_id' => $orgs['B']->getId()];
    $transfer = newTransaction('A', $transferData);
    $t = 'transactions/' . $transfer['id'];
    checkModule($transfer['from_organization_id'] === $orgs['A']->getId(), 'Origin was not assigned from employee');
    apiAs('A', 'transactions', 403, 'POST', $transferData + ['from_organization_id' => $orgs['C']->getId()]);
    apiAs('A', 'transactions', 400, 'POST', array_replace($transferData, ['to_organization_id' => $orgs['A']->getId()]));
    apiAs('A', $t . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '30']);
    apiAs('C', $t, 404); apiAs('B', $t . '/send', 403, 'POST'); apiAs('B', $t . '/items', 403, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '1']);
    apiAs('B', $t . '/receive', 409, 'POST');
    checkModule(apiAs('A', $t . '/send', 200, 'POST')['status'] === 2, 'Transfer not sent');
    apiAs('A', $t . '/send', 409, 'POST'); apiAs('A', $t . '/cancel', 409, 'POST'); apiAs('A', $t . '/receive', 403, 'POST');
    checkModule(stockQty('A', $orgs['A']->getId(), $item->getId()) === '20.000000', 'Origin available stock incorrect');
    checkModule(stockQty('A', $orgs['A']->getId(), $item->getId(), 'IN_TRANSIT') === '30.000000', 'Transit stock incorrect');
    checkModule(stockQty('B', $orgs['B']->getId(), $item->getId()) === '0.000000', 'Destination stock increased before receive');
    checkModule(apiAs('B', $t . '/receive', 200, 'POST')['status'] === 3, 'Receive failed');
    apiAs('B', $t . '/receive', 409, 'POST');
    checkModule(stockQty('B', $orgs['B']->getId(), $item->getId()) === '30.000000', 'Destination stock incorrect');
    $disposalData = ['type' => 3, 'date' => $date, 'notes' => 'Kemasan rusak'];
    apiAs('B', 'transactions', 400, 'POST', array_replace($disposalData, ['notes' => '   ']));
    $disposal = newTransaction('B', $disposalData); $d = 'transactions/' . $disposal['id'];
    apiAs('B', $d . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '5']);
    apiAs('B', $d . '/post', 200, 'POST');
    checkModule(stockQty('B', $orgs['B']->getId(), $item->getId()) === '25.000000', 'Disposal stock incorrect');
    $oversell = newTransaction('B', $disposalData); $o = 'transactions/' . $oversell['id'];
    foreach (['20', '10'] as $qty) apiAs('B', $o . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => $qty]);
    apiAs('B', $o . '/post', 409, 'POST');
    checkModule(stockQty('B', $orgs['B']->getId(), $item->getId()) === '25.000000', 'Failed posting did not roll back all lines');
    checkModule(apiAs('B', $o, 200)['status'] === 1, 'Failed posting changed status');
    apiAs('B', $o . '/cancel', 200, 'POST'); apiAs('B', $o . '/post', 409, 'POST');
    $return = newTransaction('B', ['type' => 2, 'date' => $date, 'to_organization_id' => $orgs['A']->getId(), 'reference_number' => $transfer['code'], 'notes' => 'Pengembalian']);
    $returnPath = 'transactions/' . $return['id'];
    apiAs('B', $returnPath . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '5']);
    apiAs('B', $returnPath . '/send', 200, 'POST'); apiAs('A', $returnPath . '/receive', 200, 'POST');
    checkModule(stockQty('A', $orgs['A']->getId(), $item->getId()) === '25.000000', 'Return transfer failed');
    checkModule(apiAs('C', 'transactions', 200)['total'] === 0, 'Unrelated organization can list transactions');
    apiAs('reader', $r, 200); apiAs('reader', $r . '/cancel', 403, 'POST');

    // FEFO splits one outgoing line across batches and preserves the selected batch identity.
    $fefoReceipt = newTransaction('A', array_diff_key($receiptData, ['to_organization_id' => true]));
    $fr = 'transactions/' . $fefoReceipt['id'];
    $soon = date('Y-m-d', strtotime(max($date, date('Y-m-d')) . ' +10 days'));
    $later = date('Y-m-d', strtotime(max($date, date('Y-m-d')) . ' +20 days'));
    foreach ([['EARLY', '3', $soon], ['LATER', '7', $later]] as [$batch, $qty, $expiry]) {
        apiAs('A', $fr . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => $qty, 'batch_number' => $batch . $suffix, 'expiry_date' => $expiry]);
    }
    $receivedLots = apiAs('A', $fr . '/post', 200, 'POST');
    $fefo = newTransaction('A', $transferData); $ft = 'transactions/' . $fefo['id'];
    apiAs('A', $ft . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '5']);
    $sent = apiAs('A', $ft . '/send', 200, 'POST');
    $allocated = [];
    foreach ($sent['items'][0]['allocations'] as $allocation) $allocated[$allocation['batch_number']] = $allocation['base_qty'];
    checkModule(($allocated['EARLY' . $suffix] ?? '') === '3.000000' && ($allocated['LATER' . $suffix] ?? '') === '2.000000', 'FEFO did not split across the earliest batches');
    apiAs('B', $ft . '/receive', 200, 'POST');
    $expiredLot = $db->fetchOne('SELECT id FROM stock_lots WHERE batch_number = ?', ['LATER' . $suffix]);
    $db->update('stock_lots', ['expiry_date' => '2000-01-01'], ['id' => $expiredLot]);
    $expiredTransfer = newTransaction('A', $transferData); $et = 'transactions/' . $expiredTransfer['id'];
    apiAs('A', $et . '/items', 400, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '1', 'stock_lot_id' => $expiredLot]);
    $expiredDisposal = newTransaction('A', ['type' => 3, 'date' => $date, 'notes' => 'Kedaluwarsa']); $ed = 'transactions/' . $expiredDisposal['id'];
    apiAs('A', $ed . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '1', 'stock_lot_id' => $expiredLot]);
    apiAs('A', $ed . '/post', 200, 'POST');

    // Two requests competing for the same stock must not both succeed.
    $parallel = [];
    $balance = stockQty('B', $orgs['B']->getId(), $item->getId());
    for ($i = 0; $i < 2; ++$i) {
        $tx = newTransaction('B', $disposalData);
        apiAs('B', 'transactions/' . $tx['id'] . '/items', 201, 'POST', ['item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => $balance]);
        $parallel[] = $tx['id'];
    }
    $sockets = [];
    foreach ($parallel as $id) {
        $socket = stream_socket_client('tcp://nginx:80', $errno, $error, 10);
        if (!$socket) throw new RuntimeException($error);
        fwrite($socket, "POST /api/transactions/$id/post HTTP/1.1\r\nHost: nginx\r\nAuthorization: Bearer " . $tokens['B'] . "\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
        $sockets[] = $socket;
    }
    $statuses = [];
    foreach ($sockets as $socket) {
        stream_set_timeout($socket, 30);
        $response = stream_get_contents($socket);
        fclose($socket);
        preg_match('/HTTP\/1\.1 (\d+)/', $response, $match);
        $statuses[] = (int) ($match[1] ?? 0);
    }
    sort($statuses);
    checkModule($statuses === [200, 409], 'Concurrent stock requests were not serialized');
    checkModule(stockQty('B', $orgs['B']->getId(), $item->getId()) === '0.000000', 'Concurrent posting corrupted stock');
    echo "FEFO, expiry and concurrent stock checks passed.\n";

    $autoReceipt = newTransaction('A', $receiptData);
    $autoPath = 'transactions/' . $autoReceipt['id'];
    foreach (['', '   '] as $blankBatch) {
        apiAs('A', $autoPath . '/items', 201, 'POST', array_replace($lineData, ['qty' => '1', 'batch_number' => $blankBatch]));
    }
    $autoPosted = apiAs('A', $autoPath . '/post', 200, 'POST');
    $batchNumbers = [];
    foreach ($autoPosted['items'] as $line) {
        checkModule((bool) preg_match('/^BATCH' . str_replace('-', '', $date) . '[1-9][0-9]{4}$/D', $line['batch_number']), 'Automatic batch number missing');
        checkModule($line['allocations'][0]['batch_number'] === $line['batch_number'], 'Receipt and stock batch differ');
        $batchNumbers[] = $line['batch_number'];
    }
    checkModule(count(array_unique($batchNumbers)) === 2, 'Receipt lines share an automatic batch number');

    // Admin rendering and draft editing use the same domain service as the API.
    [, $headers] = request('/admin/', 302, 'POST', http_build_query(['_username' => $users['admin']->getUsername(), '_password' => $password]), ['Content-Type: application/x-www-form-urlencoded']);
    $cookies = [];
    foreach ($headers as $header) if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $m)) $cookies[] = $m[1];
    $auth = ['Cookie: ' . implode('; ', $cookies), 'Content-Type: application/x-www-form-urlencoded'];
    [$menuHtml] = request('/admin/transactions', 200, headers: $auth);
    checkModule((bool) preg_match('~Master.*?/admin/fiscal-years.*?/admin/units~s', $menuHtml), 'Fiscal year is not first under Master'); request('/admin/transactions/stocks', 200, headers: $auth);
    foreach (['receipts' => 1, 'transfers' => 2, 'disposals' => 3] as $section => $type) {
        [$sectionHtml] = request('/admin/transactions/' . $section, 200, headers: $auth);
        checkModule(str_contains($sectionHtml, 'type="hidden" id="transaction-type" name="type" value="' . $type . '"'), 'Transaction type is not fixed by submenu');
        checkModule(!preg_match('/<select[^>]*id="transaction-type"/', $sectionHtml), 'Transaction type selector still visible');
        checkModule(str_contains($menuHtml, '/admin/transactions/' . $section), 'Submenu missing from navigation');
        if ($type !== 1) checkModule(!str_contains($sectionHtml, $receipt['code']), 'Receipt leaked into another transaction list');
        if ($type !== 2) checkModule(!str_contains($sectionHtml, $transfer['code']), 'Transfer leaked into another transaction list');
    }
    [, $createdHeaders] = request('/admin/transactions/transfers', 303, 'POST', http_build_query([
        'type' => 1, 'date' => $date, 'from_organization_id' => $orgs['A']->getId(),
        'to_organization_id' => $orgs['B']->getId(), 'reference_number' => 'MENU-' . $suffix,
    ]), $auth);
    foreach ($createdHeaders as $header) {
        if (preg_match('~^Location: /admin/transactions/([a-f0-9-]+)$~i', $header, $match)) {
            $transactions[] = $match[1];
            checkModule((int) $db->fetchOne('SELECT type FROM transactions WHERE id = ?', [$match[1]]) === 2, 'Submitted type overrode submenu type');
        }
    }
    request('/admin/' . $r, 200, headers: $auth); request('/admin/' . $t, 200, headers: $auth);
    request('/admin/' . $o, 200, headers: $auth);
    $draft = newTransaction('A', $transferData);
    request('/admin/transactions/' . $draft['id'], 200, headers: $auth);
    request('/admin/transactions/' . $draft['id'], 303, 'POST', http_build_query(['_operation' => 'line', 'item_id' => $item->getId(), 'unit_id' => $unit->getId(), 'qty' => '1']), $auth);
    [$html] = request('/admin/transactions/' . $draft['id'], 200, headers: $auth);
    checkModule(str_contains($html, 'id="line-qty" name="qty" inputmode="decimal" value=""'), 'Line form not reset');
    echo "Transaction stock, permissions, snapshots, lifecycle and admin checks passed.\n";
} finally {
    // Delete only test-owned records; never load fixtures or clear operational stock.
    foreach ($users as $user) {
        $ids = $db->fetchFirstColumn('SELECT id FROM transactions WHERE created_by_id = ?', [$user->getId()]);
        $transactions = array_merge($transactions, $ids);
    }
    $transactions = array_unique($transactions);
    foreach ($transactions as $id) $db->executeStatement('UPDATE transaction_items SET selected_lot_id = NULL WHERE transaction_id = ?', [$id]);
    foreach ($transactions as $id) {
        $db->executeStatement('DELETE FROM stock_movements WHERE allocation_id IN (SELECT a.id FROM transaction_item_allocations a JOIN transaction_items i ON i.id=a.transaction_item_id WHERE i.transaction_id=?)', [$id]);
        $db->executeStatement('DELETE FROM transaction_item_allocations WHERE transaction_item_id IN (SELECT id FROM transaction_items WHERE transaction_id=?)', [$id]);
    }
    foreach ($transactions as $id) $db->executeStatement('DELETE FROM stock_lots WHERE receipt_item_id IN (SELECT id FROM transaction_items WHERE transaction_id=?)', [$id]);
    foreach ($transactions as $id) { $db->delete('transaction_items', ['transaction_id' => $id]); $db->delete('transactions', ['id' => $id]); }
    foreach (['item_unit_conversions', 'items', 'sources', 'categories', 'units'] as $table) foreach ((array) ($masters[$table] ?? []) as $id) $db->delete($table, ['id' => $id]);
    foreach ($employees as $employee) $db->delete('employees', ['id' => $employee->getId()]);
    foreach ($users as $user) { $db->delete('core_user', ['id' => $user->getId()]); }
    foreach ($permissions as $permission) $db->delete('core_permission', ['id' => $permission->getId()]);
    foreach ($groups as $group) $db->delete('core_group', ['id' => $group->getId()]);
    foreach ($orgs as $org) $db->delete('organizations', ['id' => $org->getId()]);
    if (isset($masters['levels'])) $db->delete('levels', ['id' => $masters['levels']]);
}
