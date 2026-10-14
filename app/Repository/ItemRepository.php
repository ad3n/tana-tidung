<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Item\Model\ItemRepositoryInterface;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class ItemRepository extends AbstractRepository implements ItemRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Item::class);
    }
}
