<?php

declare(strict_types=1);

namespace KejawenLab\Application\Validation;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\Application\Entity\{Item, Unit, ItemUnitConversion};
use Symfony\Component\Validator\{Constraint, ConstraintValidator};

class UnitConfigurationValidator extends ConstraintValidator
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($value instanceof ItemUnitConversion) {
            $base = $value->getItem()?->getUnit();
            $unit = $value->getUnit();
            if (!$base || !$unit) {
                $this->context->buildViolation('Pilih satuan dasar barang dan satuan konversi terlebih dahulu.')->atPath('unit')->addViolation();
                return;
            }
            if ($base->getId() === $unit->getId()) {
                $this->context->buildViolation('Satuan dasar otomatis memiliki faktor 1; pilih satuan lain.')->atPath('unit')->addViolation();
            }
            if (!in_array($base->getType(), Unit::TYPES, true) || !in_array($unit->getType(), Unit::TYPES, true) || ($unit->getType() !== 'package' && $unit->getType() !== $base->getType())) {
                $this->context->buildViolation('Jenis satuan tidak sesuai. Konversi hanya boleh dalam jenis yang sama atau dari Kemasan ke satuan dasar.')->atPath('unit')->addViolation();
            }
        }
        if ($value instanceof Item) {
            if ($value->getUnit() && !in_array($value->getUnit()->getType(), Unit::TYPES, true)) {
                $this->context->buildViolation('Tentukan jenis satuan pada master Satuan terlebih dahulu.')->atPath('unit')->addViolation();
            }
            $original = $this->em->getUnitOfWork()->getOriginalEntityData($value);
            if (isset($original['unit']) && $original['unit'] !== $value->getUnit() && $this->em->getRepository(ItemUnitConversion::class)->count(['item' => $value]) > 0) {
                $this->context->buildViolation('Hapus konversi barang terlebih dahulu sebelum mengganti satuan dasar.')->atPath('unit')->addViolation();
            }
        }
        if ($value instanceof Unit) {
            $original = $this->em->getUnitOfWork()->getOriginalEntityData($value);
            if (isset($original['type']) && $original['type'] !== $value->getType() && $original['type'] !== 'unclassified') {
                if ($this->em->getRepository(Item::class)->count(['unit' => $value]) > 0 || $this->em->getRepository(ItemUnitConversion::class)->count(['unit' => $value]) > 0) {
                    $this->context->buildViolation('Jenis satuan tidak dapat diubah karena sudah digunakan pada barang atau konversi.')->atPath('type')->addViolation();
                }
            }
        }
    }
}
