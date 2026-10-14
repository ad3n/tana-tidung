<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use KejawenLab\Application\Category\Model\CategoryInterface;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Repository\ItemRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
#[UniqueEntity(fields: ['code'])]
#[\KejawenLab\Application\Validation\UnitConfiguration]
class Item implements ItemInterface
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
    #[Groups(['read'])]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CategoryInterface $category = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 27)]
    #[Groups(['read'])]
    #[ORM\Column(length: 27, unique: true)]
    private ?string $code = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[Groups(['read'])]
    #[ORM\Column(type: 'boolean')]
    private bool $status = false;

    #[Assert\NotNull(message: 'Pilih satuan dasar barang.')]
    #[Groups(['read'])]
    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unit $unit = null;

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function getCategory(): ?CategoryInterface
    {
        return $this->category;
    }

    public function setCategory(?CategoryInterface $category): void
    {
        $this->category = $category;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = mb_strtoupper(trim($code));
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getNullOrString(): ?string
    {
        return $this->getName();
    }
}
