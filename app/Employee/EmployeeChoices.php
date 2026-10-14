<?php

declare(strict_types=1);

namespace KejawenLab\Application\Employee;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use KejawenLab\ApiSkeleton\Entity\Group;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EmployeeChoices
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'APP_SUPER_ADMIN')]
        private readonly string $superAdmin,
    ) {
    }

    public function groups(): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('g')->from(Group::class, 'g')
            ->where('UPPER(g.code) != :superAdmin')->setParameter('superAdmin', strtoupper($this->superAdmin))
            ->orderBy('g.name', 'ASC');
    }

    public function allowsGroup(?string $code): bool
    {
        return $code !== null && strtoupper($code) !== strtoupper($this->superAdmin);
    }
}
