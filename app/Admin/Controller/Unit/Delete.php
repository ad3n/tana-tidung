<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Unit;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Unit\UnitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'UNIT', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private UnitService $service)
    {
    }

    #[Route(path: '/units/{id}/delete', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $unit = $this->service->get($id);
        if (!$unit instanceof UnitInterface) {
            $this->addFlash('error', 'sas.page.unit.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        try {
            $this->service->remove($unit);
        } catch (ConflictHttpException $error) {
            $this->addFlash('error', $error->getMessage());

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->addFlash('info', 'sas.page.unit.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
