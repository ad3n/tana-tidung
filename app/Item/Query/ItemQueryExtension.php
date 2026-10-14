<?php

declare(strict_types=1);

namespace KejawenLab\Application\Item\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use KejawenLab\Application\Item\Model\ItemInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class ItemQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.code) LIKE :itemSearch OR UPPER(%s.name) LIKE :itemSearch)', $alias, $alias))
            ->setParameter('itemSearch', '%'.StringUtil::uppercase($query).'%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(ItemInterface::class, class_implements($class));
    }
}
