<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Employee;

use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
*/
#[Permission(menu: 'EMPLOYEE', actions: [Permission::EDIT])]
final class Put extends AbstractController
{
    public function __construct(private EmployeeService $service)
    {
    }

    #[Route(path: '/employees/{id}/edit', name: self::class, methods: ['GET'], priority: 1)]
    public function __invoke(Request $request, string $id): Response
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            $this->addFlash('error', 'sas.page.employee.not_found');

            return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
        }

        $this->addFlash('admin_edit.' . Main::class, $employee->getId());

        return new RedirectResponse($this->generateUrl(Main::class, $request->query->all()));
    }
}
