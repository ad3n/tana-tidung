<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Source;

use KejawenLab\ApiSkeleton\Admin\Controller\AbstractController;
use KejawenLab\ApiSkeleton\Pagination\Paginator;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Util\CacheFactory;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Form\SourceType;
use KejawenLab\Application\Source\SourceService;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'SOURCE', actions: [Permission::VIEW])]
final class Main extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SourceService $service,
        private readonly CacheFactory $cache,
        private readonly Paginator $paginator,
    ) {
        parent::__construct($this->requestStack->getCurrentRequest(), $this->service, $this->cache, $this->paginator);
    }

    #[Route(path: '/sources', name: self::class, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $source = new Source();
        if ($request->isMethod(Request::METHOD_POST)) {
            $id = $request->getSession()->get('admin_edit.'.self::class);
            if (null !== $id) {
                $source = $this->service->get($id);
            }

            $form = $this->createForm(SourceType::class, $source);

            $form->handleRequest($request);
            if ($form->isValid()) {
                $this->service->save($form->getData());
                $this->addFlash('info', 'sas.page.source.saved');
                $request->getSession()->remove('admin_edit.'.self::class);

                return $this->redirectToRoute(self::class, $request->query->all(), Response::HTTP_SEE_OTHER);
            }

            return $this->renderList($form, $request, new ReflectionClass(Source::class));
        }

        $request->getSession()->remove('admin_edit.'.self::class);
        $flashes = $request->getSession()->getFlashBag()->get('admin_edit.'.self::class);
        foreach ($flashes as $flash) {
            $source = $this->service->get($flash);
            if (null !== $source) {
                $request->getSession()->set('admin_edit.'.self::class, $source->getId());

                break;
            }
        }

        return $this->renderList($this->createForm(SourceType::class, $source), $request, new ReflectionClass(Source::class));
    }
}
