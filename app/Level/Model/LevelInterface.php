<?php

declare(strict_types=1);

namespace KejawenLab\Application\Level\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface LevelInterface extends EntityInterface
{
    public function getName(): ?string;
}
