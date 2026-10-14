<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Source;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete as Route;
use FOS\RestBundle\View\View;
use KejawenLab\Application\Source\Model\SourceInterface;
use KejawenLab\Application\Source\SourceService;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
#[Permission(menu: 'SOURCE', actions: [Permission::DELETE])]
#[Tag(name: 'Source')]
final class Delete extends AbstractFOSRestController
{
    public function __construct(private SourceService $service, private TranslatorInterface $translator)
    {
    }

    #[Route(data: '/sources/{id}', name: self::class)]
    #[Security(name: 'Bearer')]
    #[\OpenApi\Attributes\Response(response: 204, description: 'Delete source')]
    public function __invoke(string $id): View
    {
        $source = $this->service->get($id);
        if (!$source instanceof SourceInterface) {
            throw new NotFoundHttpException($this->translator->trans('sas.page.source.not_found', [], 'pages'));
        }

        $this->service->remove($source);

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }
}
