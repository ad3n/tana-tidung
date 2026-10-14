<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Entity\User as Account;
use KejawenLab\ApiSkeleton\Security\User;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Security\Service\Authorization;
use KejawenLab\Application\Entity\{Employee, Organization, Transaction};
use KejawenLab\Application\Transaction\Enum\TransactionType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TransactionAccess
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokens,
        private Authorization $authorization,
        #[Autowire(env: 'APP_SUPER_ADMIN')] private string $superAdmin,
    ) {
    }

    public function account(): Account
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User || $user->getClass() !== Account::class) {
            throw new AccessDeniedHttpException('Transaksi hanya dapat diakses oleh pengguna aplikasi.');
        }
        $account = $this->em->find(Account::class, $user->getId());
        if (!$account) {
            throw new AccessDeniedHttpException();
        }
        return $account;
    }

    public function isAdmin(): bool
    {
        return strtoupper((string) $this->account()->getGroup()?->getCode()) === strtoupper($this->superAdmin);
    }

    public function organization(): ?Organization
    {
        if ($this->isAdmin()) {
            return null;
        }
        $employees = $this->em->getRepository(Employee::class)->findBy(['user' => $this->account()]);
        if (count($employees) !== 1 || !$employees[0]->getOrganization() || $employees[0]->getOrganization()->getDeletedAt() !== null) {
            throw new AccessDeniedHttpException('Pengguna harus terhubung ke tepat satu Pegawai dengan SKPD/OPD aktif.');
        }
        return $employees[0]->getOrganization();
    }

    public function requirePermission(string $action): void
    {
        $this->account();
        if (!$this->authorization->authorize(new Permission('TRANSACTION', [$action]))) {
            throw new AccessDeniedHttpException('Hak akses transaksi tidak mencukupi.');
        }
        $this->organization();
    }

    public function canView(Transaction $transaction): bool
    {
        $organization = $this->organization();
        return !$organization || in_array($organization->getId(), [$transaction->getFromOrganization()?->getId(), $transaction->getToOrganization()?->getId()], true);
    }

    public function canOperate(Transaction $transaction, bool $receive = false): bool
    {
        $organization = $this->organization();
        $owner = $receive || $transaction->getType() === TransactionType::RECEIPT ? $transaction->getToOrganization() : $transaction->getFromOrganization();
        return !$organization || $organization->getId() === $owner?->getId();
    }
}
