<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\Application\Entity\{Item, Unit, Organization, Source};
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransactionReferenceGuard
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function remove(EntityInterface $object, callable $remove): void
    {
        $this->locked(function () use ($object, $remove): void {
            $query = match (true) {
                $object instanceof Item => 'SELECT 1 FROM transaction_items WHERE item_id = :id LIMIT 1',
                $object instanceof Unit => 'SELECT 1 FROM transaction_items WHERE unit_id = :id OR base_unit_id = :id LIMIT 1',
                $object instanceof Organization => 'SELECT 1 FROM transactions WHERE from_organization_id = :id OR to_organization_id = :id LIMIT 1',
                $object instanceof Source => 'SELECT 1 FROM transactions WHERE source_id = :id LIMIT 1',
                default => null,
            };
            if ($query && $this->em->getConnection()->fetchOne($query, ['id' => $object->getId()])) {
                throw new ConflictHttpException('Data sudah digunakan dalam transaksi dan tidak dapat dihapus.');
            }
            $remove();
        });
    }

    public function saveItem(Item $item, callable $save): void
    {
        $this->locked(function () use ($item, $save): void {
            if ($item->getId()) {
                $base = $this->em->getConnection()->fetchOne('SELECT unit_id FROM items WHERE id = ?', [$item->getId()]);
                if ($base !== $item->getUnit()?->getId() && $this->em->getConnection()->fetchOne('SELECT 1 FROM transaction_items WHERE item_id = ? LIMIT 1', [$item->getId()])) {
                    throw new ConflictHttpException('Satuan dasar tidak dapat diubah setelah barang memiliki transaksi.');
                }
            }
            $save();
        });
    }

    private function locked(callable $operation): void
    {
        $db = $this->em->getConnection();
        $db->beginTransaction();
        try {
            $db->executeQuery('SELECT pg_advisory_xact_lock(74261015)');
            $operation();
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
