<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use KejawenLab\Application\Transaction\Enum\{TransactionType, TransactionStatus};

#[ORM\Entity]
#[ORM\Table(name: 'transaction_item_allocations')]
#[ORM\UniqueConstraint(name: 'allocation_item_lot_unique', columns: ['transaction_item_id', 'stock_lot_id'])]
class TransactionItemAllocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    public function __construct()
    {
        $this->id = \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: TransactionItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TransactionItem $transactionItem;

    #[ORM\ManyToOne(targetEntity: StockLot::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StockLot $stockLot;

    #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
    private string $baseQty;

    public function getTransactionItem(): TransactionItem
    {
        return $this->transactionItem;
    }

    public function setTransactionItem(TransactionItem $transactionItem): void
    {
        $this->transactionItem = $transactionItem;
    }

    public function getStockLot(): StockLot
    {
        return $this->stockLot;
    }

    public function setStockLot(StockLot $stockLot): void
    {
        $this->stockLot = $stockLot;
    }

    public function getBaseQty(): string
    {
        return $this->baseQty;
    }

    public function setBaseQty(string $baseQty): void
    {
        $this->baseQty = $baseQty;
    }
}
