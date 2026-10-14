<?php

declare(strict_types=1);

namespace KejawenLab\Application\Category\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class CategoryQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.name) LIKE :categorySearch)', $alias))
            ->setParameter('categorySearch', '%' . StringUtil::uppercase($query) . '%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(CategoryInterface::class, class_implements($class));
    }
}
