<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Organization;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Form\OrganizationType;
use KejawenLab\Application\Organization\OrganizationService;
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
#[Permission(menu: 'ORGANIZATION', actions: [Permission::ADD])]
#[Tag(name: 'Organization')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private OrganizationService $service)
    {
    }

    #[Route(data: '/organizations', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: OrganizationType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Organization created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Organization::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(OrganizationType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $organization = $form->getData();
        $this->service->save($organization);

        return $this->view($this->service->get($organization->getId()), Response::HTTP_CREATED);
    }
}
