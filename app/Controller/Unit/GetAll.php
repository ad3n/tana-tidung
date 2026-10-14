<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Unit;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Unit\UnitService;
use KejawenLab\ApiSkeleton\Pagination\Paginator;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
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
#[Permission(menu: 'UNIT', actions: [Permission::VIEW])]
#[Tag(name: 'Unit')]
final class GetAll extends AbstractFOSRestController
{
    public function __construct(private UnitService $service, private Paginator $paginator)
    {
    }

    #[Get(data: '/units', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Parameter(name: 'q', in: 'query', description: 'Pencarian nama atau kode', schema: new OA\Schema(type: 'string'))]
    #[Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', format: 'int32'))]
    #[Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', format: 'int32'))]
    #[Response(
        response: 200,
        description: 'Unit list',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'array',
                items: new OA\Items(ref: new Model(type: Unit::class, groups: ['read'])),
            ),
        ),
    )]
    public function __invoke(Request $request): View
    {
        return $this->view($this->paginator->paginate($this->service->getQueryBuilder(), $request, Unit::class));
    }
}
