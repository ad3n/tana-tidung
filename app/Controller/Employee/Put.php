<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Employee;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Put as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Entity\Employee;
use KejawenLab\Application\Form\EmployeeType;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::EDIT])]
#[Tag(name: 'Employee')]
final class Put extends AbstractFOSRestController
{
    public function __construct(
        private FormFactory $formFactory,
        private EmployeeService $service,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(data: '/employees/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: EmployeeType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Employee updated',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Employee::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request, string $id): View
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.employee.not_found', [], 'pages'));
        }

        $form = $this->formFactory->submitRequest(EmployeeType::class, $request, $employee);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        $this->service->save($employee);

        return $this->view($this->service->get($employee->getId()));
    }
}
