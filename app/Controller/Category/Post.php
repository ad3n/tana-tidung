<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Category;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\Application\Entity\Category;
use KejawenLab\Application\Form\CategoryType;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::ADD])]
#[Tag(name: 'Category')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private CategoryService $service)
    {
    }

    #[Route(data: '/categories', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: CategoryType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Category created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Category::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(CategoryType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $category = $form->getData();
        $this->service->save($category);

        return $this->view($this->service->get($category->getId()), Response::HTTP_CREATED);
    }
}
