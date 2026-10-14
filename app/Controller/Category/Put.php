<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Category;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Category;
use KejawenLab\Application\Form\CategoryType;
use KejawenLab\Application\Category\Model\CategoryInterface;
use KejawenLab\Application\Category\CategoryService;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'CATEGORY', actions: [Permission::EDIT])]
#[Tag(name: 'Category')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private CategoryService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/categories/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: CategoryType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Category updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Category::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $category = $this->service->get($id);
        if (!$category instanceof CategoryInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.category.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(CategoryType::class, $request, $category);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($category);

        return $this->view($this->service->get($category->getId()));
    }
}
