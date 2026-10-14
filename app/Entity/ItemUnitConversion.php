<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use KejawenLab\Application\Unit\Validator\UnitConfiguration;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'item_unit_conversions')]
#[ORM\UniqueConstraint(name: 'item_conversion_unit_unique', columns: ['item_id', 'unit_id'])]
#[UniqueEntity(fields: ['item', 'unit'], message: 'Konversi satuan ini sudah terdaftar untuk barang tersebut.')]
#[UnitConfiguration]
class ItemUnitConversion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Item $item = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Unit $unit = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,6})?$/', message: 'Isi harus berupa angka positif, maksimal 14 digit dan 6 angka desimal (gunakan titik).')]
    #[Assert\Positive]
    private string $factor = '1';

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): void
    {
        $this->item = $item;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function getFactor(): string
    {
        return $this->factor;
    }

    public function setFactor(string $factor): void
    {
        $this->factor = trim($factor);
    }
}
