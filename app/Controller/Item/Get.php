<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Item;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Item\ItemService;
use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ITEM', actions: [Permission::VIEW])]
#[Tag(name: 'Item')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private ItemService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/items/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Item detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Item::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.item.not_found', [], 'pages'));
        }

        return $this->view($item);
    }
}
