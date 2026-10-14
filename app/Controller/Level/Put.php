<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Level;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Form\LevelType;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\Application\Level\Model\LevelInterface;
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
#[Permission(menu: 'LEVEL', actions: [Permission::EDIT])]
#[Tag(name: 'Level')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private LevelService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/levels/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: LevelType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Level updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Level::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.level.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(LevelType::class, $request, $level);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($level);

        return $this->view($this->service->get($level->getId()));
    }
}
