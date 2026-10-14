<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\Cron;

use KejawenLab\ApiSkeleton\Cron\CronBuilder;
use KejawenLab\ApiSkeleton\Cron\CronService;
use KejawenLab\ApiSkeleton\Entity\Cron;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Repository\CronRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class CronServiceTest extends TestCase
{
    public function testResolve(): void
    {
        $cron = new Cron();
        $cron->setSchedule('* * * * *');

        $bus = $this->createStub(MessageBusInterface::class);

        $repository = $this->createMock(CronRepository::class);
        $repository->expects($this->once())->method('findUnRunningCrons')->willReturn([$cron]);

        $aliasHelper = new AliasHelper();

        $kernel = $this->createStub(KernelInterface::class);

        $builder = $this->createStub(CronBuilder::class);

        $service = new CronService($bus, $repository, $aliasHelper, $kernel, $builder);

        $this->assertIsArray($service->resolve());
    }
}
