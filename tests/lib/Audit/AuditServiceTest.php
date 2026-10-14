<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\Audit;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use KejawenLab\ApiSkeleton\Audit\Audit;
use KejawenLab\ApiSkeleton\Audit\AuditService;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\ApiSkeleton\Setting\SettingService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class AuditServiceTest extends TestCase
{
    /**
     * @throws InvalidArgumentException
     */
    public function testGetAudits(): void
    {
        $reader = $this->createStub(Reader::class);
        $setting = $this->createStub(SettingService::class);

        $entry = Entry::fromArray([
            'type' => 'INSERT',
            'diffs' => '{}',
            'created_at' => new \DateTimeImmutable('2021-04-03'),
        ]);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn(serialize([$entry]));

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->withAnyParameters()->willReturn($item);

        $service = new AuditService($reader, $cache, $setting);

        $entity = $this->createStub(EntityInterface::class);

        $this->assertSame(Audit::class, $service->getAudits($entity, Uuid::uuid4()->toString())::class);
    }
}
