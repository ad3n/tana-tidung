<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Repository\UnitRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: UnitRepository::class)]
#[ORM\Table(name: 'units')]
#[UniqueEntity(fields: ['code'])]
class Unit implements UnitInterface
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
