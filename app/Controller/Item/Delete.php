<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Item;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Item\ItemService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ITEM', actions: [Permission::DELETE])]
#[Tag(name: 'Item')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private ItemService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/items/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete item')]
    public function __invoke(string $id): View
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.item.not_found', [], 'pages'));
        }

        $this->service->remove($item);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
