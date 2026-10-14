<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\ApiSkeleton\Entity\User;
use KejawenLab\ApiSkeleton\Security\Model\UserInterface;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Level\Model\LevelInterface;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Repository\EmployeeRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'employees')]
#[UniqueEntity(fields: ['code'])]
class Employee implements EmployeeInterface
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

    #[Assert\Valid]
    #[Assert\NotNull]
    #[Groups(['read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserInterface $user = null;

    #[Assert\NotNull]
    #[Groups(['read'])]
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?OrganizationInterface $organization = null;

    #[Assert\NotNull]
    #[Groups(['read'])]
    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?LevelInterface $level = null;

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

    public function __construct()
    {
        $this->user = new User();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getOrganization(): ?OrganizationInterface
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationInterface $organization): void
    {
        $this->organization = $organization;
    }

    public function getLevel(): ?LevelInterface
    {
        return $this->level;
    }

    public function setLevel(?LevelInterface $level): void
    {
        $this->level = $level;
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
