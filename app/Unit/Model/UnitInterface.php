<?php

declare(strict_types=1);

namespace KejawenLab\Application\Unit\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface UnitInterface extends EntityInterface
{
    public function getType(): string;

    public function getCode(): ?string;

    public function getName(): ?string;
}
