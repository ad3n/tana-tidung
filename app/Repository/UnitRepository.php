<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Unit\Model\UnitRepositoryInterface;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Unit|null find($id, $lockMode = null, $lockVersion = null)
 * @method Unit|null findOneBy(array $criteria, array $orderBy = null)
 * @method Unit[]    findAll()
 * @method Unit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class UnitRepository extends AbstractRepository implements UnitRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Unit::class);
    }
}
