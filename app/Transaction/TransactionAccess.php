<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Entity\User as Account;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Security\Service\Authorization;
use KejawenLab\ApiSkeleton\Security\User;
use KejawenLab\Application\Entity\Employee;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Entity\Transaction;
use KejawenLab\Application\Transaction\Enum\TransactionType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
        if (!$user instanceof User || Account::class !== $user->getClass()) {
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

    public function isApprover(): bool
    {
        return 'APPRV' === strtoupper((string) $this->account()->getGroup()?->getCode());
    }

    public function isOperator(): bool
    {
        return 'OPRTR' === strtoupper((string) $this->account()->getGroup()?->getCode());
    }

    public function hasPermission(string $action): bool
    {
        return $this->authorization->authorize(new Permission('TRANSACTION', [$action]));
    }

    public function canReview(Transaction $transaction): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $owner = TransactionType::RECEIPT === $transaction->getType() ? $transaction->getToOrganization() : $transaction->getFromOrganization();

        return $this->isApprover() && $this->organization()?->getId() === $owner?->getId()
            && $this->account()->getId() !== $transaction->getCreatedBy()->getId();
    }

    public function organization(): ?Organization
    {
        if ($this->isAdmin()) {
            return null;
        }

        $employees = $this->em->getRepository(Employee::class)->findBy(['user' => $this->account()]);
        if (1 !== count($employees) || !$employees[0]->getOrganization() || null !== $employees[0]->getOrganization()->getDeletedAt()) {
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
        if ($this->isApprover() && !$this->isAdmin()) {
            return false;
        }

        $organization = $this->organization();
        $owner = $receive || TransactionType::RECEIPT === $transaction->getType() ? $transaction->getToOrganization() : $transaction->getFromOrganization();

        return !$organization || $organization->getId() === $owner?->getId();
    }
}
