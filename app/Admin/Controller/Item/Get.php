<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Item;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Item\ItemService;
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
#[Permission(menu: 'ITEM', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private ItemService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/items/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            $this->addFlash('error', 'sas.page.item.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($item);
        if ($this->reader->getProvider()->isAuditable(Item::class)) {
            $audit = $this->audit->getAudits($item, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Item::class));
    }
}
