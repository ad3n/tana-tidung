<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Category;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\Application\Category\Model\CategoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private CategoryService $service)
    {
    }

    #[Route(path: '/categories/{id}/delete', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            $this->addFlash('error', 'sas.page.category.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->service->remove($category);

        $this->addFlash('info', 'sas.page.category.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
