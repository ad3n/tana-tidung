<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Employee;

use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\Application\Entity\Employee;
use KejawenLab\Application\Form\EmployeeType;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\ApiSkeleton\Pagination\Paginator;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::VIEW])]
final class Main extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EmployeeService $service,
        private readonly CacheFactory $cache,
        private readonly Paginator $paginator,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache, $this->paginator);
    }

    #[Route(path: '/employees', name: self::class, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $employee = new Employee();
        if ($request->isMethod(Request::METHOD_POST)) {
            $id = $request->getSession()->get('admin_edit.' . self::class);
            if (null !== $id) {
                $employee = $this->service->get($id);
            }

            $form = $this->createForm(EmployeeType::class, $employee);

            $form->handleRequest($request);
            if ($form->isValid()) {
                $this->service->save($form->getData());
                $this->addFlash('info', 'sas.page.employee.saved');
                $request->getSession()->remove('admin_edit.' . self::class);

                return $this->redirectToRoute(self::class, $request->query->all(), Response::HTTP_SEE_OTHER);
            }

            return $this->renderList($form, $request, new ReflectionClass(Employee::class));
        }

        $request->getSession()->remove('admin_edit.' . self::class);
        $flashes = $request->getSession()->getFlashBag()->get('admin_edit.' . self::class);
        foreach ($flashes as $flash) {
            $employee = $this->service->get($flash);
            if (null !== $employee) {
                $request->getSession()->set('admin_edit.' . self::class, $employee->getId());

                break;
            }
        }

        return $this->renderList($this->createForm(EmployeeType::class, $employee), $request, new ReflectionClass(Employee::class));
    }
}
