<?php

declare(strict_types=1);

namespace KejawenLab\Application\Unit\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class UnitQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.code) LIKE :unitSearch OR UPPER(%s.name) LIKE :unitSearch)', $alias, $alias))
            ->setParameter('unitSearch', '%' . StringUtil::uppercase($query) . '%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(UnitInterface::class, class_implements($class));
    }
}
