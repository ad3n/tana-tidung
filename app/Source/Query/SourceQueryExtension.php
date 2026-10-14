<?php

declare(strict_types=1);

namespace KejawenLab\Application\Source\Query;

use Doctrine\ORM\QueryBuilder;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\ApiSkeleton\Pagination\Query\AbstractQueryExtension;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class SourceQueryExtension extends AbstractQueryExtension
{
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $alias = $this->aliasHelper->findAlias('root');
        $queryBuilder->andWhere(sprintf('(UPPER(%s.name) LIKE :sourceSearch)', $alias))
            ->setParameter('sourceSearch', '%' . StringUtil::uppercase($query) . '%');
    }

    public function support(string $class, Request $request): bool
    {
        return in_array(SourceInterface::class, class_implements($class));
    }
}
