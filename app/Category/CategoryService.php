<?php

declare(strict_types=1);

namespace KejawenLab\Application\Category;

use KejawenLab\Application\Category\Model\CategoryRepositoryInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class CategoryService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, CategoryRepositoryInterface $repository, AliasHelper $aliasHelper)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }
}
