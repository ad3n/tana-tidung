<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Employee;

use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::DELETE])]
final class Delete extends AbstractController
{
    public function __construct(private EmployeeService $service)
    {
    }

    #[Route(path: '/employees/{id}/delete', name: self::class, methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            $this->addFlash('error', 'sas.page.employee.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $this->service->remove($employee);

        $this->addFlash('info', 'sas.page.employee.deleted');

        return new RedirectResponse($this->generateUrl(Main::class));
    }
}
