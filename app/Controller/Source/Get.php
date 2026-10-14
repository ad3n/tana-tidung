<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Source;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Get as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Source\SourceService;
use KejawenLab\Application\Source\Model\SourceInterface;
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
#[Permission(menu: 'SOURCE', actions: [Permission::VIEW])]
#[Tag(name: 'Source')]
final class Get extends AbstractFOSRestController
{
    public function __construct(private SourceService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/sources/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[Response(
        response: 200,
        description: 'Source detail',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(ref: new Model(type: Source::class, groups: ['read']), type: 'object'),
        ),
    )]
    public function __invoke(string $id): View
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.source.not_found', [], 'pages'));
        }

        return $this->view($source);
    }
}
