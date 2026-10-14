<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\FiscalYear;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private FiscalYearService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/fiscal-years/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            $this->addFlash('error', 'sas.page.fiscalyear.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($fiscalyear);
        if ($this->reader->getProvider()->isAuditable(FiscalYear::class)) {
            $audit = $this->audit->getAudits($fiscalyear, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(FiscalYear::class));
    }
}
