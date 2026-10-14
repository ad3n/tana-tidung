<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Level;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Level;
use KejawenLab\Application\Level\LevelService;
use KejawenLab\Application\Level\Model\LevelInterface;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'LEVEL', actions: [Permission::VIEW])]
#[Tag(name: 'Level')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private LevelService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/levels/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Level detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Level::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $level = $this->service->get($id);
        if (!$level instanceof LevelInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.level.not_found', [], 'pages'));
        }

        return $this->view($level);
    }
}
