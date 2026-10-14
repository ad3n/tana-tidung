<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Item;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Form\ItemType;
use KejawenLab\Application\Item\ItemService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ITEM', actions: [Permission::ADD])]
#[Tag(name: 'Item')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private ItemService $service)
    {
    }

    #[Route(data: '/items', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: ItemType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Item created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Item::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(ItemType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $item = $form->getData();
        $this->service->save($item);

        return $this->view($this->service->get($item->getId()), Response::HTTP_CREATED);
    }
}
