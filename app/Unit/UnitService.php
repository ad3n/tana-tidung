<?php

declare(strict_types=1);

namespace KejawenLab\Application\Unit;

use KejawenLab\Application\Unit\Model\UnitRepositoryInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class UnitService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, UnitRepositoryInterface $repository, AliasHelper $aliasHelper, private \Doctrine\ORM\EntityManagerInterface $em, private \KejawenLab\Application\Transaction\TransactionReferenceGuard $transactionGuard)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }

    public function remove(\KejawenLab\ApiSkeleton\Entity\EntityInterface $object): void
    {
        $used = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM items i WHERE i.deleted_at IS NULL AND (i.unit_id = :id OR EXISTS (SELECT 1 FROM item_unit_conversions c WHERE c.item_id = i.id AND c.unit_id = :id))',
            ['id' => $object->getId()],
        );
        if ((int) $used > 0) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Satuan masih digunakan oleh barang atau konversi.');
        }
        $this->transactionGuard->remove($object, fn () => parent::remove($object));
    }
}
