<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Employee;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::DELETE])]
#[Tag(name: 'Employee')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private EmployeeService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/employees/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete employee')]
    public function __invoke(string $id): View
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.employee.not_found', [], 'pages'));
        }

        $this->service->remove($employee);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
