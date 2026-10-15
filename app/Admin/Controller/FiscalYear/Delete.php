<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\FiscalYear;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private FiscalYearService $service)
    {
    }

    #[Route(path: '/fiscal-years/{id}/delete', name: self::class, methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            $this->addFlash('error', 'sas.page.fiscalyear.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        try {
            $this->service->remove($fiscalyear);
        } catch (ConflictHttpException $error) {
            $this->addFlash('error', $error->getMessage());

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->addFlash('info', 'sas.page.fiscalyear.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
