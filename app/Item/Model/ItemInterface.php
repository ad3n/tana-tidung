<?php

declare(strict_types=1);

namespace KejawenLab\Application\Item\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\Application\Category\Model\CategoryInterface;

interface ItemInterface extends EntityInterface
{
    public function getUnit(): ?\KejawenLab\Application\Entity\Unit;

    public function getCode(): ?string;

    public function getName(): ?string;
    public function getCategory(): ?CategoryInterface;

    public function getStatus(): bool;
}
