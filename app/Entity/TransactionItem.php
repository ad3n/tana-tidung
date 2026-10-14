<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'transaction_items')]
class TransactionItem
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

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Transaction $transaction;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Item $item;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Unit $unit;

    #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
    private string $qty;

    #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
    private string $conversionFactor;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Unit $baseUnit;

    #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
    private string $baseQty;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $batchNumber = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $expiryDate = null;

    #[ORM\ManyToOne(targetEntity: StockLot::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?StockLot $selectedLot = null;

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item): void
    {
        $this->item = $item;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function getQty(): string
    {
        return $this->qty;
    }

    public function setQty(string $qty): void
    {
        $this->qty = $qty;
    }

    public function getConversionFactor(): string
    {
        return $this->conversionFactor;
    }

    public function setConversionFactor(string $conversionFactor): void
    {
        $this->conversionFactor = $conversionFactor;
    }

    public function getBaseUnit(): Unit
    {
        return $this->baseUnit;
    }

    public function setBaseUnit(Unit $baseUnit): void
    {
        $this->baseUnit = $baseUnit;
    }

    public function getBaseQty(): string
    {
        return $this->baseQty;
    }

    public function setBaseQty(string $baseQty): void
    {
        $this->baseQty = $baseQty;
    }

    public function getBatchNumber(): ?string
    {
        return $this->batchNumber;
    }

    public function setBatchNumber(?string $batchNumber): void
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

    public function getSelectedLot(): ?StockLot
    {
        return $this->selectedLot;
    }

    public function setSelectedLot(?StockLot $selectedLot): void
    {
        $this->selectedLot = $selectedLot;
    }
}
