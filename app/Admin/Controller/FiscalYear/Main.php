<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\FiscalYear;

use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\Form\FiscalYearType;
use KejawenLab\Application\FiscalYear\FiscalYearService;
use KejawenLab\ApiSkeleton\Pagination\Paginator;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use ReflectionClass;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'FISCALYEAR', actions: [Permission::VIEW])]
final class Main extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly FiscalYearService $service,
        private readonly CacheFactory $cache,
        private readonly Paginator $paginator,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache, $this->paginator);
    }

    #[Route(path: '/fiscal-years', name: self::class, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $fiscalyear = new FiscalYear();
        if ($request->isMethod(Request::METHOD_POST)) {
            $id = $request->getSession()->get('admin_edit.' . self::class);
            if (null !== $id) {
                $fiscalyear = $this->service->get($id);
            }

            $form = $this->createForm(FiscalYearType::class, $fiscalyear);

            $form->handleRequest($request);
            if ($form->isValid()) {
                try {
                    $this->service->save($form->getData());
                } catch (ConflictHttpException $error) {
                    $form->addError(new FormError($error->getMessage()));

                    return $this->renderList($form, $request, new ReflectionClass(FiscalYear::class));
                }
                $this->addFlash('info', 'sas.page.fiscalyear.saved');
                $request->getSession()->remove('admin_edit.' . self::class);

                return $this->redirectToRoute(self::class, $request->query->all(), Response::HTTP_SEE_OTHER);
            }

            return $this->renderList($form, $request, new ReflectionClass(FiscalYear::class));
        }

        $request->getSession()->remove('admin_edit.' . self::class);
        $flashes = $request->getSession()->getFlashBag()->get('admin_edit.' . self::class);
        foreach ($flashes as $flash) {
            $fiscalyear = $this->service->get($flash);
            if (null !== $fiscalyear) {
                $request->getSession()->set('admin_edit.' . self::class, $fiscalyear->getId());

                break;
            }
        }

        return $this->renderList($this->createForm(FiscalYearType::class, $fiscalyear), $request, new ReflectionClass(FiscalYear::class));
    }
}
