<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\Application\Entity\Category;
use KejawenLab\Application\Category\Model\CategoryRepositoryInterface;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class CategoryRepository extends AbstractRepository implements CategoryRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Category::class);
    }
}
