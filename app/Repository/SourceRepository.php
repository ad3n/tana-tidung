<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Source\Model\SourceRepositoryInterface;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Source|null find($id, $lockMode = null, $lockVersion = null)
 * @method Source|null findOneBy(array $criteria, array $orderBy = null)
 * @method Source[]    findAll()
 * @method Source[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class SourceRepository extends AbstractRepository implements SourceRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Source::class);
    }
}
