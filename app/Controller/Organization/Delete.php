<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Organization;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ORGANIZATION', actions: [Permission::DELETE])]
#[Tag(name: 'Organization')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private OrganizationService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/organizations/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete organization')]
    public function __invoke(string $id): View
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.organization.not_found', [], 'pages'));
        }

        $this->service->remove($organization);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
