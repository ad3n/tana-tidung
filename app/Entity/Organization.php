<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Repository\OrganizationRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use SplObjectStorage;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
#[UniqueEntity(fields: ['code'])]
class Organization implements OrganizationInterface
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

    #[Groups(['read'])]
    #[MaxDepth(1)]
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?OrganizationInterface $parent = null;

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

    public function getParent(): ?OrganizationInterface
    {
        return $this->parent;
    }

    public function setParent(?OrganizationInterface $parent): void
    {
        $this->parent = $parent;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = mb_strtoupper(trim($code));
    }

    #[Assert\Callback]
    public function validateHierarchy(ExecutionContextInterface $context): void
    {
        $seen = new SplObjectStorage();
        $seen->attach($this);
        $parent = $this->parent;
        while (null !== $parent) {
            if ($seen->contains($parent) || (null !== $this->getId() && $parent->getId() === $this->getId())) {
                $context->buildViolation('SKPD/OPD induk tidak boleh membentuk hubungan melingkar.')
                    ->atPath('parent')->addViolation();

                return;
            }

            $seen->attach($parent);
            $parent = $parent->getParent();
        }
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
