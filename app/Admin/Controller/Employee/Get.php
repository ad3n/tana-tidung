<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Employee;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Audit\Audit as Record;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Employee\EmployeeService;
use KejawenLab\Application\Employee\Model\EmployeeInterface;
use KejawenLab\Application\Entity\Employee;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'EMPLOYEE', actions: [Permission::VIEW])]
final class Get extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private EmployeeService $service,
        private readonly CacheFactory $cache,
        private AuditService $audit,
        private Reader $reader,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache);
    }

    #[Route(path: '/employees/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $employee = $this->service->get($id);
        if (!$employee instanceof EmployeeInterface) {
            $this->addFlash('error', 'sas.page.employee.not_found');

            return new RedirectResponse($this->generateUrl(Main::class));
        }

        $audit = new Record($employee);
        if ($this->reader->getProvider()->isAuditable(Employee::class)) {
            $audit = $this->audit->getAudits($employee, $id, 1);
        }

        return $this->renderDetail($audit, new ReflectionClass(Employee::class));
    }
}
