<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Organization;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ORGANIZATION', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private OrganizationService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/organizations/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            $this->addFlash('error', 'sas.page.organization.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($organization);
        if ($this->reader->getProvider()->isAuditable(Organization::class)) {
            $audit = $this->audit->getAudits($organization, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Organization::class));
    }
}
