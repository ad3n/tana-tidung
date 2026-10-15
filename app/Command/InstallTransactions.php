<?php

declare(strict_types=1);

namespace KejawenLab\Application\Command;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Entity\Menu;
use KejawenLab\ApiSkeleton\Entity\Permission;
use KejawenLab\Application\Admin\Controller\Transaction\Listing;
use KejawenLab\Application\Admin\Controller\Transaction\Main;
use KejawenLab\Application\Admin\Controller\Transaction\Stock;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:transactions:install', description: 'Install transaction menu, permissions and database constraints without reloading fixtures.')]
final class InstallTransactions extends Command
{
    public function __construct(private EntityManagerInterface $em, private TransactionService $transactions, #[Autowire(env: 'APP_SUPER_ADMIN')] private string $superAdmin)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->em->wrapInTransaction(function (): void {
            $this->em->getConnection()->executeStatement('ALTER TABLE transaction_items ADD COLUMN IF NOT EXISTS price NUMERIC(20,2) DEFAULT NULL');
            $this->em->getConnection()->executeStatement('UPDATE transaction_items i SET price = 10000000.00 FROM transactions t WHERE i.transaction_id = t.id AND t.type = 1 AND i.price IS NULL');
            $this->em->getConnection()->executeStatement("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS workflow_history JSON NOT NULL DEFAULT '[]'");
            $this->em->getConnection()->executeStatement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transaction_status_valid');
            $this->em->getConnection()->executeStatement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS movement_valid');
            $this->em->getConnection()->executeStatement("UPDATE transactions SET status = 7, workflow_history = (workflow_history::jsonb || jsonb_build_array(jsonb_build_object('action', 'return', 'status', 7, 'actor_id', NULL, 'actor_name', 'Sistem', 'at', CURRENT_TIMESTAMP, 'note', 'Draft lama dipindahkan untuk dilengkapi dan diajukan.')))::json WHERE status = 1");
            $checks = [
                'transactions' => [
                    'transaction_type_valid' => 'type IN (1,2,3)',
                    'transaction_status_valid' => 'status IN (2,3,4,5,6,7) AND (status <> 2 OR type = 2) AND (status <> 6 OR type <> 1)',
                    'transaction_organizations_valid' => '(type = 1 AND from_organization_id IS NULL AND to_organization_id IS NOT NULL AND source_id IS NOT NULL) OR (type = 2 AND from_organization_id IS NOT NULL AND to_organization_id IS NOT NULL AND from_organization_id <> to_organization_id AND source_id IS NULL) OR (type = 3 AND from_organization_id IS NOT NULL AND to_organization_id IS NULL AND source_id IS NULL AND notes IS NOT NULL AND LENGTH(TRIM(notes)) > 0)',
                ],
                'transaction_items' => [
                    'transaction_quantities_positive' => 'qty > 0 AND conversion_factor > 0 AND base_qty > 0 AND base_qty = qty * conversion_factor',
                    'transaction_price_nonnegative' => 'price IS NULL OR price >= 0',
                ],
                'transaction_item_allocations' => ['allocation_quantity_positive' => 'base_qty > 0'],
                'stock_movements' => ['movement_valid' => "quantity_delta <> 0 AND location IN ('AVAILABLE','RESERVED','IN_TRANSIT') AND phase IN ('post','receive','reserve','send','release')"],
            ];
            foreach ($checks as $table => $constraints) {
                foreach ($constraints as $name => $expression) {
                    if (!$this->em->getConnection()->fetchOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name])) {
                        $this->em->getConnection()->executeStatement("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($expression)");
                    }
                }
            }

            $this->transactions->reserveApprovedTransfers();
            $menu = $this->em->getRepository(Menu::class)->findOneBy(['code' => 'TRANSACTION']);
            if (!$menu) {
                $menu = new Menu();
                $menu->setCode('TRANSACTION');
                $menu->setName('Transaksi');
                $menu->setSortOrder(3);
                $menu->setIconClass('cil-swap-horizontal');
                $menu->setRouteName('#');
                $this->em->persist($menu);
            }

            $menu->setRouteName('#');
            $children = [];
            foreach (['receipt' => 'Penerimaan', 'transfer' => 'Transfer', 'disposal' => 'Pengeluaran', 'receipt_list' => 'Daftar Penerimaan', 'transfer_list' => 'Daftar Transfer', 'disposal_list' => 'Daftar Pengeluaran', 'stock' => 'Stok Barang'] as $key => $name) {
                $code = 'TRANSACTION_'.strtoupper($key);
                $child = $this->em->getRepository(Menu::class)->findOneBy(['code' => $code]);
                if (!$child) {
                    $child = new Menu();
                    $child->setCode($code);
                    $this->em->persist($child);
                }

                $child->setParent($menu);
                $child->setName($name);
                $child->setSortOrder(count($children));
                $child->setAdminOnly(true);
                $route = Main::class.'_'.$key;
                if (str_ends_with($key, '_list')) {
                    $route = Listing::class.'_'.substr($key, 0, -5);
                }

                if ('stock' === $key) {
                    $route = Stock::class;
                }

                $child->setRouteName($route);
                $children[] = $child;
            }

            foreach ($this->em->getRepository(Group::class)->findAll() as $group) {
                $permission = $this->em->getRepository(Permission::class)->findOneBy(['group' => $group, 'menu' => $menu]);
                if (!$permission) {
                    $permission = new Permission();
                    $permission->setGroup($group);
                    $permission->setMenu($menu);
                    $allowed = strtoupper((string) $group->getCode()) === strtoupper($this->superAdmin);
                    $permission->setViewable($allowed);
                    $permission->setAddable($allowed);
                    $permission->setEditable($allowed);
                    $permission->setDeletable($allowed);
                    $this->em->persist($permission);
                }

                foreach ($children as $child) {
                    if ($this->em->getRepository(Permission::class)->findOneBy(['group' => $group, 'menu' => $child])) {
                        continue;
                    }

                    $childPermission = new Permission();
                    $childPermission->setGroup($group);
                    $childPermission->setMenu($child);
                    $childPermission->setViewable($permission->isViewable());
                    $childPermission->setAddable(!str_ends_with($child->getCode(), '_LIST') && 'TRANSACTION_STOCK' !== $child->getCode() && $permission->isAddable());
                    $childPermission->setEditable(!str_ends_with($child->getCode(), '_LIST') && 'TRANSACTION_STOCK' !== $child->getCode() && $permission->isEditable());
                    $childPermission->setDeletable(!str_ends_with($child->getCode(), '_LIST') && 'TRANSACTION_STOCK' !== $child->getCode() && $permission->isDeletable());
                    $this->em->persist($childPermission);
                }
            }

            $this->em->flush();
            $this->syncWorkflowPermissions();
        });
        $this->em->getConfiguration()->getResultCache()?->clear();
        $output->writeln('Transaction menu and constraints installed. Configure non-admin groups through Group permissions.');

        return Command::SUCCESS;
    }

    private function syncWorkflowPermissions(): void
    {
        $directory = dirname(__DIR__, 2).'/fixtures/';
        $menuCodes = [];
        foreach (Yaml::parseFile($directory.'menu.yaml') as $fixture) {
            $menuCodes[$fixture['ref']] = $fixture['code'];
        }

        $fixtures = Yaml::parseFile($directory.'permission.yaml');
        foreach (['approver' => ['APPRV', 'Approver'], 'operator' => ['OPRTR', 'Operator']] as $ref => [$code, $name]) {
            $group = $this->em->getRepository(Group::class)->findOneBy(['code' => $code]);
            if (!$group) {
                $group = new Group();
                $group->setCode($code);
                $group->setName($name);
                $this->em->persist($group);
                $this->em->flush();
            }

            $expected = [];
            foreach ($fixtures as $fixture) {
                if (str_ends_with($fixture['group'], '#'.$ref)) {
                    $expected[$menuCodes[explode('#', $fixture['menu'])[1]]] = $fixture;
                }
            }

            foreach ($this->em->getRepository(Menu::class)->findAll() as $menu) {
                $permissions = $this->em->getRepository(Permission::class)->findBy(['group' => $group, 'menu' => $menu]);
                $permission = array_shift($permissions);
                foreach ($permissions as $duplicate) {
                    $this->em->remove($duplicate);
                }

                if (!$permission) {
                    $permission = new Permission();
                    $permission->setGroup($group);
                    $permission->setMenu($menu);
                    $this->em->persist($permission);
                }

                $flags = $expected[$menu->getCode()] ?? [];
                $permission->setAddable($flags['addable'] ?? false);
                $permission->setEditable($flags['editable'] ?? false);
                $permission->setViewable($flags['viewable'] ?? false);
                $permission->setDeletable($flags['deletable'] ?? false);
            }
        }
    }
}
