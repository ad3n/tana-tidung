<?php

declare(strict_types=1);

namespace KejawenLab\Application\Organization\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class OrganizationQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.code) LIKE :organizationSearch OR UPPER(%s.name) LIKE :organizationSearch)', $alias, $alias))
            ->setParameter('organizationSearch', '%' . StringUtil::uppercase($query) . '%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(OrganizationInterface::class, class_implements($class));
    }
}
