<?php

declare(strict_types=1);

namespace KejawenLab\Application\Employee;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Security\Service\UserService;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use KejawenLab\Application\Employee\Model\EmployeeRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class EmployeeService extends AbstractService implements ServiceInterface
{
    public function __construct(
        MessageBusInterface $messageBus,
        EmployeeRepositoryInterface $repository,
        AliasHelper $aliasHelper,
        private readonly EntityManagerInterface $em,
        private readonly UserService $users,
    ) {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }

    public function save(EntityInterface $object): void
    {
        $this->em->wrapInTransaction(function () use ($object): void {
            $object->getUser()->setFullName($object->getName() ?? '');
            $this->users->save($object->getUser());
            parent::save($object);
        });
    }
}
