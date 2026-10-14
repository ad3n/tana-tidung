<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use KejawenLab\Application\Transaction\Enum\{TransactionType, TransactionStatus};

#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
class Transaction
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

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $code;

    #[ORM\Column(type: 'smallint', enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: 'smallint', enumType: TransactionStatus::class)]
    private TransactionStatus $status = TransactionStatus::DRAFT;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\ManyToOne(targetEntity: FiscalYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FiscalYear $fiscalYear;

    #[ORM\ManyToOne(targetEntity: Source::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Source $source = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Organization $fromOrganization = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Organization $toOrganization = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: \KejawenLab\ApiSkeleton\Entity\User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private \KejawenLab\ApiSkeleton\Entity\User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: \KejawenLab\ApiSkeleton\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?\KejawenLab\ApiSkeleton\Entity\User $postedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $postedAt = null;

    #[ORM\ManyToOne(targetEntity: \KejawenLab\ApiSkeleton\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?\KejawenLab\ApiSkeleton\Entity\User $receivedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\ManyToOne(targetEntity: \KejawenLab\ApiSkeleton\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?\KejawenLab\ApiSkeleton\Entity\User $cancelledBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): void
    {
        $this->status = $status;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getFiscalYear(): FiscalYear
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(FiscalYear $fiscalYear): void
    {
        $this->fiscalYear = $fiscalYear;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): void
    {
        $this->source = $source;
    }

    public function getFromOrganization(): ?Organization
    {
        return $this->fromOrganization;
    }

    public function setFromOrganization(?Organization $fromOrganization): void
    {
        $this->fromOrganization = $fromOrganization;
    }

    public function getToOrganization(): ?Organization
    {
        return $this->toOrganization;
    }

    public function setToOrganization(?Organization $toOrganization): void
    {
        $this->toOrganization = $toOrganization;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): void
    {
        $this->referenceNumber = $referenceNumber;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedBy(): \KejawenLab\ApiSkeleton\Entity\User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(\KejawenLab\ApiSkeleton\Entity\User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getPostedBy(): ?\KejawenLab\ApiSkeleton\Entity\User
    {
        return $this->postedBy;
    }

    public function setPostedBy(?\KejawenLab\ApiSkeleton\Entity\User $postedBy): void
    {
        $this->postedBy = $postedBy;
    }

    public function getPostedAt(): ?\DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function setPostedAt(?\DateTimeImmutable $postedAt): void
    {
        $this->postedAt = $postedAt;
    }

    public function getReceivedBy(): ?\KejawenLab\ApiSkeleton\Entity\User
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?\KejawenLab\ApiSkeleton\Entity\User $receivedBy): void
    {
        $this->receivedBy = $receivedBy;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): void
    {
        $this->receivedAt = $receivedAt;
    }

    public function getCancelledBy(): ?\KejawenLab\ApiSkeleton\Entity\User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?\KejawenLab\ApiSkeleton\Entity\User $cancelledBy): void
    {
        $this->cancelledBy = $cancelledBy;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }
}
