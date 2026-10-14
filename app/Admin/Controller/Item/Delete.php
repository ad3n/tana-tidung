<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Item;

use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Item\ItemService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ITEM', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private ItemService $service)
    {
    }

    #[Route(path: '/items/{id}/delete', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            $this->addFlash('error', 'sas.page.item.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        try {
            $this->service->remove($item);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error) {
            $this->addFlash('error', $error->getMessage());
            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->addFlash('info', 'sas.page.item.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
