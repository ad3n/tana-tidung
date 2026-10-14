<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\EventSubscriber;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\EventSubscriber\LogoutSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class LogoutSubscriberTest extends TestCase
{
    public function testNoRedirect(): void
    {
        $request = Request::createFromGlobals();

        $event = $this->createMock(LogoutEvent::class);
        $event->expects($this->once())->method('getRequest')->willReturn($request);
        $event->expects($this->never())->method('setResponse');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $configuration = new Configuration();
        $configuration->setQueryCache($cache);
        $configuration->setResultCache($cache);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        $apiClientRequest = new LogoutSubscriber($urlGenerator, $cache, $entityManager);
        $apiClientRequest->redirect($event);
    }

    public function testNoRedirectBecauseNotAdminContext(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getPathInfo')->willReturn('/api');

        $event = $this->createMock(LogoutEvent::class);
        $event->expects($this->once())->method('getRequest')->willReturn($request);
        $event->expects($this->never())->method('setResponse');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $configuration = new Configuration();
        $configuration->setQueryCache($cache);
        $configuration->setResultCache($cache);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        $apiClientRequest = new LogoutSubscriber($urlGenerator, $cache, $entityManager);
        $apiClientRequest->redirect($event);
    }

    public function testRedirect(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getPathInfo')->willReturn('/admin');

        $event = $this->createMock(LogoutEvent::class);
        $event->expects($this->once())->method('getRequest')->willReturn($request);
        $event->expects($this->once())->method('setResponse');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->willReturn('/admin/fake');

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $configuration = new Configuration();
        $configuration->setQueryCache($cache);
        $configuration->setResultCache($cache);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        $apiClientRequest = new LogoutSubscriber($urlGenerator, $cache, $entityManager);
        $apiClientRequest->redirect($event);
    }

    public function testGetEventClass(): void
    {
        $events = LogoutSubscriber::getSubscribedEvents();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey(LogoutEvent::class, $events);
    }
}
