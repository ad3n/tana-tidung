<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction\Enum;

enum TransactionType: int
{
    case RECEIPT = 1;
    case TRANSFER = 2;
    case DISPOSAL = 3;

    public function label(): string
    {
        return 'transaction.type.'.$this->value;
    }
}
