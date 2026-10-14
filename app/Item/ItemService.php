<?php

declare(strict_types=1);

namespace KejawenLab\Application\Item;

use KejawenLab\Application\Item\Model\ItemRepositoryInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class ItemService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, ItemRepositoryInterface $repository, AliasHelper $aliasHelper)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }
}
