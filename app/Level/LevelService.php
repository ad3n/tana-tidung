<?php

declare(strict_types=1);

namespace KejawenLab\Application\Level;

use KejawenLab\Application\Level\Model\LevelRepositoryInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class LevelService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, LevelRepositoryInterface $repository, AliasHelper $aliasHelper)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }
}
