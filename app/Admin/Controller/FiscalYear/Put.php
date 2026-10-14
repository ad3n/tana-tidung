<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\FiscalYear;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\Application\FiscalYear\Model\FiscalYearInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private FiscalYearService $service)
    {
    }

    #[Route(path: '/fiscal-years/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $fiscalyear = $this->service->get($id);
        if (!$fiscalyear instanceof FiscalYearInterface) {
            $this->addFlash('error', 'sas.page.fiscalyear.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.'.Main::class, $fiscalyear->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
