<?php

declare(strict_types=1);

namespace KejawenLab\Application\Organization\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface OrganizationInterface extends EntityInterface
{
    public function getParent(): ?OrganizationInterface;

    public function getCode(): ?string;

    public function getName(): ?string;
}
