<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Source;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Form\SourceType;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
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
#[Permission(menu: 'SOURCE', actions: [Permission::ADD])]
#[Tag(name: 'Source')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private SourceService $service)
    {
    }

    #[Route(data: '/sources', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: SourceType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Source created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Source::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(SourceType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var SourceInterface $source */
        $source = $form->getData();
        $this->service->save($source);

        return $this->view($this->service->get($source->getId()), Response::HTTP_CREATED);
    }
}
