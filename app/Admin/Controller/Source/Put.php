<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Source;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'SOURCE', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private SourceService $service)
    {
    }

    #[Route(path: '/sources/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            $this->addFlash('error', 'sas.page.source.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.'.Main::class, $source->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
