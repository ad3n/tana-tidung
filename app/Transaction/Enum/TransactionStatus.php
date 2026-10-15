<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction\Enum;

enum TransactionStatus: int
{
    case SENT = 2;
    case COMPLETED = 3;
    case CANCELLED = 4;
    case SUBMITTED = 5;
    case APPROVED = 6;
    case RETURNED = 7;

    public function label(): string
    {
        return 'transaction.status.'.$this->value;
    }
}
