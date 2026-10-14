<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Employee;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Entity\Employee;
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
#[Permission(menu: 'EMPLOYEE', actions: [Permission::VIEW])]
#[Tag(name: 'Employee')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private EmployeeService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/employees/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Employee detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Employee::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.employee.not_found', [], 'pages'));
        }

        return $this->view($employee);
    }
}
