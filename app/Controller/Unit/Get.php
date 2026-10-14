<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Unit;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Unit\UnitService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'UNIT', actions: [Permission::VIEW])]
#[Tag(name: 'Unit')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private UnitService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/units/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Unit detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Unit::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $unit = $this->service->get($id);
        if (!$unit instanceof UnitInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.unit.not_found', [], 'pages'));
        }

        return $this->view($unit);
    }
}
