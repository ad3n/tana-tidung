<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Unit;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Unit\Model\UnitInterface;
use KejawenLab\Application\Unit\UnitService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'UNIT', actions: [Permission::DELETE])]
#[Tag(name: 'Unit')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private UnitService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/units/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete unit')]
    public function __invoke(string $id): View
    {
        $unit = $this->service->get($id);
        if (!$unit instanceof UnitInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.unit.not_found', [], 'pages'));
        }

        $this->service->remove($unit);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
