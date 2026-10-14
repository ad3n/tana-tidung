<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\FiscalYear;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::VIEW])]
#[Tag(name: 'FiscalYear')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private FiscalYearService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/fiscal-years/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'FiscalYear detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: FiscalYear::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.fiscalyear.not_found', [], 'pages'));
        }

        return $this->view($fiscalyear);
    }
}
