<?php

declare(strict_types=1);

namespace KejawenLab\Application\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AdminRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrf)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ControllerEvent::class => ['validate', 20]];
    }

    public function validate(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/admin') || $request->isMethodSafe()) {
            return;
        }

        $token = $request->headers->get('X-CSRF-Token', $request->request->get('_csrf_token'));
        if (!is_string($token) || !$this->csrf->isTokenValid(new CsrfToken('admin_action', $token))) {
            throw new AccessDeniedHttpException('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        }

        $request->request->remove('_csrf_token');
    }
}
