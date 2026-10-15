<?php

declare(strict_types=1);

namespace KejawenLab\Application\Tests\Security;

use KejawenLab\ApiSkeleton\Admin\Controller\Group\Main;
use KejawenLab\ApiSkeleton\EventSubscriber\PermissionSubscriber;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Security\Authorization\Ownership;
use KejawenLab\ApiSkeleton\Security\Service\Authorization;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MutationPermissionTest extends TestCase
{
    public function testCreateRequiresAddBeyondView(): void
    {
        $this->assertMutationDenied(false);
    }

    public function testEditRequiresEditBeyondView(): void
    {
        $this->assertMutationDenied(true);
    }

    private function assertMutationDenied(bool $editing): void
    {
        $request = Request::create('/admin/groups', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        if ($editing) {
            $request->getSession()->set('admin_edit.'.Main::class, 'existing-group');
        }

        $actions = [];
        $authorization = $this->createMock(Authorization::class);
        $authorization->expects($this->exactly(2))->method('authorize')->willReturnCallback(static function (Permission $permission) use (&$actions): bool {
            $actions[] = $permission->getActions();

            return [Permission::VIEW] === $permission->getActions();
        });
        $controller = (new ReflectionClass(Main::class))->newInstanceWithoutConstructor();
        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [$controller, '__invoke'], $request, HttpKernelInterface::MAIN_REQUEST);
        try {
            (new PermissionSubscriber($authorization, $this->createStub(Ownership::class)))->validate($event);
            self::fail('Read-only request was allowed to mutate');
        } catch (AccessDeniedException) {
            self::assertSame([[Permission::VIEW], [$editing ? Permission::EDIT : Permission::ADD]], $actions);
        }
    }
}
