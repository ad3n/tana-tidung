<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Level;

use KejawenLab\Application\Level\Model\LevelInterface;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
*/
#[Permission(menu: 'LEVEL', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private LevelService $service)
    {
    }

    #[Route(path: '/levels/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            $this->addFlash('error', 'sas.page.level.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('id', $level->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
