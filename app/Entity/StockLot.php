<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_lots')]
class StockLot
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Item $item;

    #[ORM\ManyToOne(targetEntity: TransactionItem::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private TransactionItem $receiptItem;

    #[ORM\Column(type: 'string', length: 100)]
    private string $batchNumber;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $expiryDate = null;

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item): void
    {
        $this->item = $item;
    }

    public function getReceiptItem(): TransactionItem
    {
        return $this->receiptItem;
    }

    public function setReceiptItem(TransactionItem $receiptItem): void
    {
        $this->receiptItem = $receiptItem;
    }

    public function getBatchNumber(): string
    {
        return $this->batchNumber;
    }

    public function setBatchNumber(string $batchNumber): void
    {
        $this->batchNumber = $batchNumber;
    }

    public function getExpiryDate(): ?DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?DateTimeImmutable $expiryDate): void
    {
        $this->expiryDate = $expiryDate;
    }
}
