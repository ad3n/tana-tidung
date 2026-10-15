<?php

declare(strict_types=1);

namespace KejawenLab\Application\Tests\Security;

use KejawenLab\Application\Security\AdminRequestSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AdminRequestSubscriberTest extends TestCase
{
    public function testMissingTokenIsRejected(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->never())->method('isTokenValid');
        $this->expectException(AccessDeniedHttpException::class);
        (new AdminRequestSubscriber($manager))->validate($this->event(Request::create('/admin/groups', 'POST')));
    }

    public function testInvalidTokenIsRejected(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->once())->method('isTokenValid')->willReturn(false);
        $this->expectException(AccessDeniedHttpException::class);
        (new AdminRequestSubscriber($manager))->validate($this->event(Request::create('/admin/transactions/123', 'POST', ['_csrf_token' => 'forged'])));
    }

    public function testValidTokenIsRemovedBeforeController(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->once())->method('isTokenValid')->with($this->callback(static fn ($token): bool => 'admin_action' === $token->getId() && 'valid' === $token->getValue()))->willReturn(true);
        $request = Request::create('/admin/groups', 'POST', ['_csrf_token' => 'valid', 'name' => 'Keep']);
        (new AdminRequestSubscriber($manager))->validate($this->event($request));
        self::assertSame(['name' => 'Keep'], $request->request->all());
    }

    public function testApiAndReadRequestsDoNotRequireSessionTokens(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->never())->method('isTokenValid');
        $subscriber = new AdminRequestSubscriber($manager);
        $subscriber->validate($this->event(Request::create('/api/transactions', 'POST')));
        $subscriber->validate($this->event(Request::create('/admin/groups', 'GET')));
    }

    private function event(Request $request): ControllerEvent
    {
        return new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn () => null, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
