<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\FiscalYear;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Pagination\Paginator;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::VIEW])]
#[Tag(name: 'FiscalYear')]
final class GetAll extends AbstractFOSRestController
{
    public function __construct(private FiscalYearService $service, private Paginator $paginator)
    {
    }

    #[Get(data: '/fiscal-years', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Parameter(name: 'q', in: 'query', description: 'Tahun empat digit', schema: new OA\Schema(type: 'string'))]
    #[Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', format: 'int32'))]
    #[Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', format: 'int32'))]
    #[Response(
        response: 200,
        description: 'FiscalYear list',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'array',
                items: new OA\Items(ref: new Model(type: FiscalYear::class, groups: ['read'])),
            ),
        ),
    )]
    public function __invoke(Request $request): View
    {
        return $this->view($this->paginator->paginate($this->service->getQueryBuilder(), $request, FiscalYear::class));
    }
}
