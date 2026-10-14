<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use KejawenLab\Application\Repository\FiscalYearRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: FiscalYearRepository::class)]
#[ORM\Table(name: 'fiscal_years')]
#[UniqueEntity(fields: ['year'])]
class FiscalYear implements FiscalYearInterface
{
    use BlameableEntity;
    use SoftDeleteableEntity;
    use TimestampableEntity;

    #[Groups(['read'])]
    #[OA\Property(type: 'string')]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    private ?UuidInterface $id = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1000, max: 9999)]
    #[Groups(['read'])]
    #[ORM\Column(type: 'integer', unique: true)]
    private ?int $year = null;

    #[Groups(['read'])]
    #[ORM\Column(type: 'boolean')]
    private bool $status = false;

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): void
    {
        $this->year = $year;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }

    public function getNullOrString(): ?string
    {
        return $this->year === null ? null : (string) $this->year;
    }
}
