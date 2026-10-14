<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\Model\FiscalYearRepositoryInterface;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method FiscalYear|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiscalYear|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiscalYear[]    findAll()
 * @method FiscalYear[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class FiscalYearRepository extends AbstractRepository implements FiscalYearRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, FiscalYear::class);
    }
}
