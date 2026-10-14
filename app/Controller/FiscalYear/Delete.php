<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\FiscalYear;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::DELETE])]
#[Tag(name: 'FiscalYear')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private FiscalYearService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/fiscal-years/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete fiscalyear')]
    public function __invoke(string $id): View
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.fiscalyear.not_found', [], 'pages'));
        }

        $this->service->remove($fiscalyear);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
