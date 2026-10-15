<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Source;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'SOURCE', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private SourceService $service)
    {
    }

    #[Route(path: '/sources/{id}/delete', name: self::class, methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            $this->addFlash('error', 'sas.page.source.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        try {
            $this->service->remove($source);
        } catch (ConflictHttpException $error) {
            $this->addFlash('error', $error->getMessage());

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->addFlash('info', 'sas.page.source.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
