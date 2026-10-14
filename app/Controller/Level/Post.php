<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Level;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Form\LevelType;
use KejawenLab\Application\Level\Model\LevelInterface;
use KejawenLab\Application\Level\LevelService;
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
#[Permission(menu: 'LEVEL', actions: [Permission::ADD])]
#[Tag(name: 'Level')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private LevelService $service)
    {
    }

    #[Route(data: '/levels', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: LevelType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Level created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Level::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(LevelType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var LevelInterface $level */
        $level = $form->getData();
        $this->service->save($level);

        return $this->view($this->service->get($level->getId()), Response::HTTP_CREATED);
    }
}
