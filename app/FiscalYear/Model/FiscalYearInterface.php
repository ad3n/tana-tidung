<?php

declare(strict_types=1);

namespace KejawenLab\Application\FiscalYear\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

interface FiscalYearInterface extends EntityInterface
{
    public function getYear(): ?int;
    public function getStatus(): bool;
}
