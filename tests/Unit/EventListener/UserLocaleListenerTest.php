<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\EventListener;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\EventListener\UserLocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\LocaleSwitcher;

final class UserLocaleListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ], UserLocaleListener::getSubscribedEvents());
    }

    public function testItSetsMainRequestLocaleFromAuthenticatedUser(): void
    {
        $user = new User();
        $user->setLocale('fr');

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects($this->once())->method('setLocale')->with('fr');
        $listener = new UserLocaleListener($this->createTokenStorage($user), $localeSwitcher);
        $request = Request::create('/admin');

        $listener->onKernelRequest(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('fr', $request->getLocale());
    }

    public function testItIgnoresSubRequests(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->never())->method('getToken');
        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects($this->never())->method('setLocale');
        $listener = new UserLocaleListener($tokenStorage, $localeSwitcher);
        $request = Request::create('/admin');
        $request->setLocale('en');

        $listener->onKernelRequest(new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('en', $request->getLocale());
    }

    public function testItIgnoresMissingToken(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);
        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects($this->never())->method('setLocale');
        $listener = new UserLocaleListener($tokenStorage, $localeSwitcher);
        $request = Request::create('/admin');
        $request->setLocale('en');

        $listener->onKernelRequest(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('en', $request->getLocale());
    }

    public function testItIgnoresNonCoreUsers(): void
    {
        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects($this->never())->method('setLocale');
        $listener = new UserLocaleListener($this->createTokenStorage(new InMemoryUser('admin', null)), $localeSwitcher);
        $request = Request::create('/admin');
        $request->setLocale('en');

        $listener->onKernelRequest(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('en', $request->getLocale());
    }

    private function createTokenStorage(mixed $user): TokenStorageInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }
}
