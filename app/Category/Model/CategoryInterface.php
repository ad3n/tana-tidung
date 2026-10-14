<?php

declare(strict_types=1);

namespace KejawenLab\Application\Category\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface CategoryInterface extends EntityInterface
{
    public function getName(): ?string;
}
