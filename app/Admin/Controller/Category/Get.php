<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Category;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\Application\Entity\Category;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private CategoryService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/categories/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            $this->addFlash('error', 'sas.page.category.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($category);
        if ($this->reader->getProvider()->isAuditable(Category::class)) {
            $audit = $this->audit->getAudits($category, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Category::class));
    }
}
