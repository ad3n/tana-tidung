<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Organization\Model\OrganizationRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class OrganizationRepository extends AbstractRepository implements OrganizationRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Organization::class);
    }
}
