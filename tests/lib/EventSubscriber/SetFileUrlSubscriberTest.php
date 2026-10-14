<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\EventSubscriber;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use KejawenLab\ApiSkeleton\Entity\Media;
use KejawenLab\ApiSkeleton\EventSubscriber\SetFileUrlSubscriber;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class SetFileUrlSubscriberTest extends TestCase
{
    public function testValidObject(): void
    {
        $media = new Media();

        $event = $this->createStub(PostLoadEventArgs::class);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())->method('resolveUri')->willReturn('/fake/url');

        $subscriber = new SetFileUrlSubscriber($storage);

        $subscriber->postLoad($media, $event);

        $this->assertSame($media->getFileUrl(), '/fake/url');
    }

    public function testListenerRegistration(): void
    {
        $reflection = new \ReflectionClass(SetFileUrlSubscriber::class);
        $attributes = $reflection->getAttributes(\Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(Events::postLoad, $attributes[0]->newInstance()->event);
    }
}
