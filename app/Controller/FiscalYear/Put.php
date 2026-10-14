<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\FiscalYear;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use KejawenLab\Application\Form\FiscalYearType;
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
#[Permission(menu: 'FISCALYEAR', actions: [Permission::EDIT])]
#[Tag(name: 'FiscalYear')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private FiscalYearService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/fiscal-years/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: FiscalYearType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'FiscalYear updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: FiscalYear::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.fiscalyear.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(FiscalYearType::class, $request, $fiscalyear);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($fiscalyear);

        return $this->view($this->service->get($fiscalyear->getId()));
    }
}
