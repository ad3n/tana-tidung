<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\Model\FiscalYearRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class FiscalYearRepository extends AbstractRepository implements FiscalYearRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, FiscalYear::class);
    }
}
