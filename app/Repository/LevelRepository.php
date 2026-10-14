<?php

declare(strict_types=1);

namespace KejawenLab\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use KejawenLab\ApiSkeleton\Repository\AbstractRepository;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Level\Model\LevelRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class LevelRepository extends AbstractRepository implements LevelRepositoryInterface
{
    public function __construct(RequestStack $requestStack, ManagerRegistry $registry)
    {
        parent::__construct($requestStack, $registry, Level::class);
    }
}
