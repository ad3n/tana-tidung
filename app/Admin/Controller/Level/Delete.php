<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Level;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\Application\Level\Model\LevelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'LEVEL', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private LevelService $service)
    {
    }

    #[Route(path: '/levels/{id}/delete', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            $this->addFlash('error', 'sas.page.level.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->service->remove($level);

        $this->addFlash('info', 'sas.page.level.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
