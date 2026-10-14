<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_movements')]
#[ORM\UniqueConstraint(name: 'movement_once', columns: ['allocation_id', 'organization_id', 'location', 'phase'])]
class StockMovement
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

    #[ORM\ManyToOne(targetEntity: TransactionItemAllocation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TransactionItemAllocation $allocation;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: StockLot::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StockLot $stockLot;

    #[ORM\Column(type: 'string', length: 12)]
    private string $location;

    #[ORM\Column(type: 'string', length: 12)]
    private string $phase;

    #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
    private string $quantityDelta;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $postedAt;

    public function getAllocation(): TransactionItemAllocation
    {
        return $this->allocation;
    }

    public function setAllocation(TransactionItemAllocation $allocation): void
    {
        $this->allocation = $allocation;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function getStockLot(): StockLot
    {
        return $this->stockLot;
    }

    public function setStockLot(StockLot $stockLot): void
    {
        $this->stockLot = $stockLot;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): void
    {
        $this->phase = $phase;
    }

    public function getQuantityDelta(): string
    {
        return $this->quantityDelta;
    }

    public function setQuantityDelta(string $quantityDelta): void
    {
        $this->quantityDelta = $quantityDelta;
    }

    public function getPostedAt(): DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function setPostedAt(DateTimeImmutable $postedAt): void
    {
        $this->postedAt = $postedAt;
    }
}
