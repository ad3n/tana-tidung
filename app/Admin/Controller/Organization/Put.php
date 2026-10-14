<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Organization;

use KejawenLab\Application\Organization\Model\OrganizationInterface;
use KejawenLab\Application\Organization\OrganizationService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
*/
#[Permission(menu: 'ORGANIZATION', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private OrganizationService $service)
    {
    }

    #[Route(path: '/organizations/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $organization = $this->service->get($id);
        if (!$organization instanceof OrganizationInterface) {
            $this->addFlash('error', 'sas.page.organization.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.' . Main::class, $organization->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
