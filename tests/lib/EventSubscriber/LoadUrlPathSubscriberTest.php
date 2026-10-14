<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use KejawenLab\ApiSkeleton\Entity\Menu;
use KejawenLab\ApiSkeleton\EventSubscriber\LoadUrlPathSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class LoadUrlPathSubscriberTest extends TestCase
{
    public function testIsAdminOnly(): void
    {
        $menu = new Menu();
        $menu->setAdminOnly(true);
        $menu->setRouteName('#');

        $event = $this->createStub(PostLoadEventArgs::class);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new LoadUrlPathSubscriber($urlGenerator);

        $subscriber->postLoad($menu, $event);
    }

    public function testAdminNotFoundRoute(): void
    {
        $menu = new Menu();
        $menu->setAdminOnly(true);
        $menu->setRouteName('fake_route');

        $event = $this->createStub(PostLoadEventArgs::class);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->willThrowException(new RouteNotFoundException());

        $subscriber = new LoadUrlPathSubscriber($urlGenerator);

        $subscriber->postLoad($menu, $event);
        $this->assertSame('#', $menu->getAdminPath());
    }

    public function testNonAdmin(): void
    {
        $menu = new Menu();
        $menu->setAdminOnly(false);
        $menu->setRouteName('#');

        $event = $this->createStub(PostLoadEventArgs::class);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->willThrowException(new RouteNotFoundException());

        $subscriber = new LoadUrlPathSubscriber($urlGenerator);

        $subscriber->postLoad($menu, $event);
        $this->assertSame('#', $menu->getAdminPath());
    }

    public function testNonAdminNotFound(): void
    {
        $menu = new Menu();
        $menu->setAdminOnly(false);
        $menu->setRouteName('fake_route');

        $event = $this->createStub(PostLoadEventArgs::class);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->exactly(2))->method('generate')->willThrowException(new RouteNotFoundException());

        $subscriber = new LoadUrlPathSubscriber($urlGenerator);

        $subscriber->postLoad($menu, $event);
        $this->assertSame('#', $menu->getAdminPath());
    }

    public function testListenerRegistration(): void
    {
        $reflection = new ReflectionClass(LoadUrlPathSubscriber::class);
        $attributes = $reflection->getAttributes(AsEntityListener::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(Events::postLoad, $attributes[0]->newInstance()->event);
    }
}
