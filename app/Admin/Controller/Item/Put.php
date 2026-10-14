<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Item;

use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Item\ItemService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
*/
#[Permission(menu: 'ITEM', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private ItemService $service)
    {
    }

    #[Route(path: '/items/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            $this->addFlash('error', 'sas.page.item.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.' . Main::class, $item->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
