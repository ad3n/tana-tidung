<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Unit;

use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Unit\UnitService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
*/
#[Permission(menu: 'UNIT', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private UnitService $service)
    {
    }

    #[Route(path: '/units/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $unit = $this->service->get($id);
        if (!$unit instanceof UnitInterface) {
            $this->addFlash('error', 'sas.page.unit.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.' . Main::class, $unit->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
