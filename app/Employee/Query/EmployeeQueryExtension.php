<?php

declare(strict_types=1);

namespace KejawenLab\Application\Employee\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class EmployeeQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.code) LIKE :employeeSearch OR UPPER(%s.name) LIKE :employeeSearch)', $alias, $alias))
            ->setParameter('employeeSearch', '%' . StringUtil::uppercase($query) . '%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(EmployeeInterface::class, class_implements($class));
    }
}
