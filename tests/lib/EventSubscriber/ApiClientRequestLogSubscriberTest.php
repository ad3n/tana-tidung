<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\EventSubscriber;

use KejawenLab\ApiSkeleton\ApiClient\Model\ApiClientInterface;
use KejawenLab\ApiSkeleton\EventSubscriber\ApiClientRequestLogSubscriber;
use KejawenLab\ApiSkeleton\Security\Service\UserProviderFactory;
use KejawenLab\ApiSkeleton\Security\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class ApiClientRequestLogSubscriberTest extends TestCase
{
    public function testOnlyMainRequestCanPass(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->never())->method('getToken');

        $messageBus = $this->createStub(MessageBusInterface::class);
        $userProvider = $this->createStub(UserProviderFactory::class);

        $event = $this->createMock(ResponseEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(false);

        $event->method('getResponse')->willReturn(new Response());

        $apiClientRequest = new ApiClientRequestLogSubscriber($tokenStorage, $userProvider, $messageBus);
        $apiClientRequest->requestLog($event);
    }

    public function testNullToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn(null);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $userProvider = $this->createStub(UserProviderFactory::class);

        $event = $this->createMock(ResponseEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(true);

        $event->method('getResponse')->willReturn(new Response());

        $apiClientRequest = new ApiClientRequestLogSubscriber($tokenStorage, $userProvider, $messageBus);
        $apiClientRequest->requestLog($event);
    }

    public function testTokenReturnNonValidUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getUser')->willReturn($this->createStub(UserInterface::class));

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);

        $messageBus = $this->createStub(MessageBusInterface::class);

        $userProvider = $this->createMock(UserProviderFactory::class);
        $userProvider->expects($this->never())->method('getRealUser');

        $event = $this->createMock(ResponseEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(true);

        $event->method('getResponse')->willReturn(new Response());

        $apiClientRequest = new ApiClientRequestLogSubscriber($tokenStorage, $userProvider, $messageBus);
        $apiClientRequest->requestLog($event);
    }

    public function testTokenReturnValidUser(): void
    {
        $user = new User();

        $apiClient = $this->createMock(ApiClientInterface::class);
        $apiClient->expects($this->once())->method('getId')->willReturn('test');

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);

        $messageBus = $this->createStub(MessageBusInterface::class);

        $userProvider = $this->createMock(UserProviderFactory::class);
        $userProvider->expects($this->once())->method('getRealUser')->willReturn($apiClient);

        $event = $this->createMock(ResponseEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(true);
        $event->expects($this->once())->method('getRequest')->willReturn(Request::createFromGlobals());

        $event->method('getResponse')->willReturn(new Response());

        $apiClientRequest = new ApiClientRequestLogSubscriber($tokenStorage, $userProvider, $messageBus);
        $apiClientRequest->requestLog($event);
    }

    public function testGetEventClass(): void
    {
        $events = ApiClientRequestLogSubscriber::getSubscribedEvents();

        $this->assertCount(2, $events);
        $this->assertArrayHasKey(ResponseEvent::class, $events);
    }
}
