<?php

declare(strict_types=1);

namespace KejawenLab\Application\Security;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Entity\Permission;
use KejawenLab\ApiSkeleton\Entity\User;
use KejawenLab\ApiSkeleton\Security\User as AuthUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsDoctrineListener(event: Events::onFlush)]
final class PrivilegeGuard
{
    public function __construct(private TokenStorageInterface $tokens, private RequestStack $requests, #[Autowire(env: 'APP_SUPER_ADMIN')] private string $superAdmin)
    {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if (!$this->requests->getMainRequest()) {
            return;
        }

        $actor = $this->tokens->getToken()?->getUser();
        if (!$actor instanceof AuthUser) {
            return;
        }

        $em = $event->getObjectManager();
        $actorGroup = $em->getConnection()->fetchOne('SELECT g.code FROM core_user u JOIN core_group g ON g.id=u.group_id WHERE u.id=?', [$actor->getId()]);
        if (User::class === $actor->getClass() && strtoupper((string) $actorGroup) === strtoupper($this->superAdmin)) {
            return;
        }

        $uow = $em->getUnitOfWork();
        foreach (array_merge($uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()) as $entity) {
            $old = $uow->getOriginalEntityData($entity);
            if ($entity instanceof Group && ($this->isSuper($entity) || strtoupper((string) ($old['code'] ?? '')) === strtoupper($this->superAdmin))) {
                throw new AccessDeniedHttpException('Grup superadmin hanya dapat dikelola oleh superadmin.');
            }

            if ($entity instanceof User && ($this->isSuper($entity->getGroup()) || $this->isSuper($old['group'] ?? null))) {
                throw new AccessDeniedHttpException('Akun superadmin hanya dapat dikelola oleh superadmin.');
            }

            if ($entity instanceof User && $entity->getId() === $actor->getId() && isset($uow->getEntityChangeSet($entity)['group'])) {
                throw new AccessDeniedHttpException('Pengguna tidak dapat mengganti grupnya sendiri.');
            }

            if ($entity instanceof Permission && ($entity->isAddable() || $entity->isEditable() || $entity->isViewable() || $entity->isDeletable() || $uow->isScheduledForUpdate($entity) || $uow->isScheduledForDelete($entity))) {
                throw new AccessDeniedHttpException('Perubahan hak akses hanya dapat dilakukan oleh superadmin.');
            }
        }
    }

    private function isSuper(?Group $group): bool
    {
        return $group && strtoupper((string) $group->getCode()) === strtoupper($this->superAdmin);
    }
}
