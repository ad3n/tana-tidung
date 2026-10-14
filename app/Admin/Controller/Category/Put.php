<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Category;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\Application\Category\Model\CategoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private CategoryService $service)
    {
    }

    #[Route(path: '/categories/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            $this->addFlash('error', 'sas.page.category.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.'.Main::class, $category->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
