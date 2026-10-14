<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Organization;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ORGANIZATION', actions: [Permission::VIEW])]
#[Tag(name: 'Organization')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private OrganizationService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/organizations/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Organization detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Organization::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.organization.not_found', [], 'pages'));
        }

        return $this->view($organization);
    }
}
