<?php

declare(strict_types=1);

namespace KejawenLab\Application\FiscalYear\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class FiscalYearQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->orderBy($alias . '.year', 'DESC');
        $year = $request->query->get('q');
        if ($year === null || $year === '') {
            return;
        }
        if (!preg_match('/^[1-9][0-9]{3}$/D', $year)) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }
        $queryBuilder->andWhere($alias . '.year = :fiscalYearSearch')->setParameter('fiscalYearSearch', (int) $year);
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(FiscalYearInterface::class, class_implements($class));
    }
}
