<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Source;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
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
#[Permission(menu: 'SOURCE', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private SourceService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/sources/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            $this->addFlash('error', 'sas.page.source.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($source);
        if ($this->reader->getProvider()->isAuditable(Source::class)) {
            $audit = $this->audit->getAudits($source, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Source::class));
    }
}
