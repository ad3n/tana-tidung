<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Item;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Form\ItemType;
use KejawenLab\Application\Item\Model\ItemInterface;
use KejawenLab\Application\Item\ItemService;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ITEM', actions: [Permission::EDIT])]
#[Tag(name: 'Item')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private ItemService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/items/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: ItemType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Item updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Item::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $item = $this->service->get($id);
        if (!$item instanceof ItemInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.item.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(ItemType::class, $request, $item);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($item);

        return $this->view($this->service->get($item->getId()));
    }
}
