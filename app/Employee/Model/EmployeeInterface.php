<?php

declare(strict_types=1);

namespace KejawenLab\Application\Employee\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\ApiSkeleton\Security\Model\UserInterface;
use KejawenLab\Application\Level\Model\LevelInterface;
use KejawenLab\Application\Organization\Model\OrganizationInterface;

interface EmployeeInterface extends EntityInterface
{
    public function getUser(): ?UserInterface;

    public function getOrganization(): ?OrganizationInterface;

    public function getLevel(): ?LevelInterface;

    public function getCode(): ?string;

    public function getName(): ?string;
}
