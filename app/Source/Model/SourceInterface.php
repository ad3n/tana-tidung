<?php

declare(strict_types=1);

namespace KejawenLab\Application\Source\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface SourceInterface extends EntityInterface
{
    public function getName(): ?string;
}
