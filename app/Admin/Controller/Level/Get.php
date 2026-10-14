<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Level;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\Application\Level\Model\LevelInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'LEVEL', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private LevelService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/levels/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            $this->addFlash('error', 'sas.page.level.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($level);
        if ($this->reader->getProvider()->isAuditable(Level::class)) {
            $audit = $this->audit->getAudits($level, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Level::class));
    }
}
