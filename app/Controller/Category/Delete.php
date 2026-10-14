<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Category;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::DELETE])]
#[Tag(name: 'Category')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private CategoryService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/categories/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete category')]
    public function __invoke(string $id): View
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.category.not_found', [], 'pages'));
        }

        $this->service->remove($category);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
