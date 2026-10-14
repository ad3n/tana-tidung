<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Unit;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Form\UnitType;
use KejawenLab\Application\Unit\UnitService;
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
#[Permission(menu: 'UNIT', actions: [Permission::ADD])]
#[Tag(name: 'Unit')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private UnitService $service)
    {
    }

    #[Route(data: '/units', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: UnitType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Unit created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Unit::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(UnitType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $unit = $form->getData();
        $this->service->save($unit);

        return $this->view($this->service->get($unit->getId()), Response::HTTP_CREATED);
    }
}
