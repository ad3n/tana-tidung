<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Unit;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Unit\UnitService;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'UNIT', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private UnitService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/units/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $unit = $this->service->get($id);
        if (!$unit instanceof UnitInterface) {
            $this->addFlash('error', 'sas.page.unit.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($unit);
        if ($this->reader->getProvider()->isAuditable(Unit::class)) {
            $audit = $this->audit->getAudits($unit, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Unit::class));
    }
}
