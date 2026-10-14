<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\Cron;

use Cron\Report\CronReport;
use KejawenLab\ApiSkeleton\Cron\CronService;
use KejawenLab\ApiSkeleton\Cron\Executor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Redis;
use ReflectionException;
use ReflectionObject;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class ExecutorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testStartProcesses(): void
    {
        $redis = $this->createStub(Redis::class);

        $service = $this->createStub(CronService::class);
        $service->method('get');
        $service->method('save');

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info');

        $report = new CronReport();

        $executor = new Executor($redis, $service, $logger);

        $reflection = new ReflectionObject($executor);
        $method = $reflection->getMethod('startProcesses');
        $method->setAccessible(true);

        $method->invokeArgs($executor, [$report]);

        $this->assertSame($report, $report);
    }
}
