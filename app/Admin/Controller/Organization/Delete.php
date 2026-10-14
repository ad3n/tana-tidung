<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Organization;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'ORGANIZATION', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private OrganizationService $service)
    {
    }

    #[Route(path: '/organizations/{id}/delete', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            $this->addFlash('error', 'sas.page.organization.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        try {
            $this->service->remove($organization);
        } catch (ConflictHttpException $error) {
            $this->addFlash('error', $error->getMessage());

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->addFlash('info', 'sas.page.organization.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
