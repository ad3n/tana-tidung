<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Category;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Category;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::VIEW])]
#[Tag(name: 'Category')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private CategoryService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/categories/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Category detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Category::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.category.not_found', [], 'pages'));
        }

        return $this->view($category);
    }
}
