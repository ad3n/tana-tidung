<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Level;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\Application\Level\Model\LevelInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'LEVEL', actions: [Permission::DELETE])]
#[Tag(name: 'Level')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private LevelService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/levels/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete level')]
    public function __invoke(string $id): View
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.level.not_found', [], 'pages'));
        }

        $this->service->remove($level);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
