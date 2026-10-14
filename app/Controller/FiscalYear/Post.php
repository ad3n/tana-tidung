<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\FiscalYear;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\Form\FiscalYearType;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use KejawenLab\Application\FiscalYear\FiscalYearService;
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
#[Permission(menu: 'FISCALYEAR', actions: [Permission::ADD])]
#[Tag(name: 'FiscalYear')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private FiscalYearService $service)
    {
    }

    #[Route(data: '/fiscal-years', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: FiscalYearType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'FiscalYear created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: FiscalYear::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(FiscalYearType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var FiscalYearInterface $fiscalyear */
        $fiscalyear = $form->getData();
        $this->service->save($fiscalyear);

        return $this->view($this->service->get($fiscalyear->getId()), Response::HTTP_CREATED);
    }
}
