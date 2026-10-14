<?php

declare(strict_types=1);

namespace KejawenLab\Application\Item\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\Application\Entity\Unit;

interface ItemInterface extends EntityInterface
{
    public function getUnit(): ?Unit;

    public function getCode(): ?string;

    public function getName(): ?string;

    public function getCategory(): ?CategoryInterface;

    public function getStatus(): bool;
}
