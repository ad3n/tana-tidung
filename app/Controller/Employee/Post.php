<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Employee;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Post as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Employee;
use KejawenLab\Application\Form\EmployeeType;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\ApiSkeleton\Form\FormFactory;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::ADD])]
#[Tag(name: 'Employee')]
final class Post extends AbstractFOSRestController
{
    public function __construct(private FormFactory $formFactory, private EmployeeService $service)
    {
    }

    #[Route(data: '/employees', name: self::class)]
    #[Security(name: 'Bearer')]
    #[RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: EmployeeType::class), type: 'object'),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Employee created',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Employee::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(Request $request): View
    {
        $form = $this->formFactory->submitRequest(EmployeeType::class, $request);
        if (!$form->isValid()) {
            return $this->view((array) $form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var EmployeeInterface $employee */
        $employee = $form->getData();
        $this->service->save($employee);

        return $this->view($this->service->get($employee->getId()), Response::HTTP_CREATED);
    }
}
