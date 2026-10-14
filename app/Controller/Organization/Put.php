<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Organization;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Form\OrganizationType;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
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
#[Permission(menu: 'ORGANIZATION', actions: [Permission::EDIT])]
#[Tag(name: 'Organization')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private OrganizationService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/organizations/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: OrganizationType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Organization updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Organization::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.organization.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(OrganizationType::class, $request, $organization);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($organization);

        return $this->view($this->service->get($organization->getId()));
    }
}
