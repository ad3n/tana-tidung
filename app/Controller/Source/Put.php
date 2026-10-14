<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Source;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Form\SourceType;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
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
#[Permission(menu: 'SOURCE', actions: [Permission::EDIT])]
#[Tag(name: 'Source')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private SourceService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/sources/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: SourceType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Source updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Source::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.source.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(SourceType::class, $request, $source);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($source);

        return $this->view($this->service->get($source->getId()));
    }
}
