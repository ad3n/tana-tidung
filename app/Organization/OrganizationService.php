<?php

declare(strict_types=1);

namespace KejawenLab\Application\Organization;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use KejawenLab\Application\Organization\Model\OrganizationRepositoryInterface;
use KejawenLab\Application\Transaction\TransactionReferenceGuard;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class OrganizationService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, OrganizationRepositoryInterface $repository, AliasHelper $aliasHelper, private TransactionReferenceGuard $transactionGuard)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }

    public function remove(EntityInterface $object): void
    {
        $this->transactionGuard->remove($object, fn () => parent::remove($object));
    }
}
