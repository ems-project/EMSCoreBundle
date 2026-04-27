<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\EventListener;

use EMS\CoreBundle\EventListener\RequestListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testItAllowsInternalRedirectTargets(): void
    {
        $listener = $this->createListener();
        $request = Request::create('/login', Request::METHOD_GET, ['redirectToUrl' => '/dashboard?tab=welcome#intro']);
        $response = new RedirectResponse('/default');
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        self::assertSame('/dashboard?tab=welcome#intro', $response->getTargetUrl());
    }

    public function testItRejectsUnsafeRedirectTargets(): void
    {
        $listener = $this->createListener();

        foreach ([
            'https://evil.example.com',
            '//evil.example.com',
            '/\\evil.example.com',
            "/safe\r\nLocation:https://evil.example.com",
            'dashboard',
        ] as $redirectToUrl) {
            $request = Request::create('/login', Request::METHOD_GET, ['redirectToUrl' => $redirectToUrl]);
            $response = new RedirectResponse('/default');
            $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

            $listener->onKernelResponse($event);

            self::assertSame('/default', $response->getTargetUrl(), \sprintf('Failed for redirect target "%s"', $redirectToUrl));
        }
    }

    public function testItDoesNotChangeNonRedirectResponses(): void
    {
        $listener = $this->createListener();
        $request = Request::create('/login', Request::METHOD_GET, ['redirectToUrl' => '/dashboard']);
        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        self::assertSame($response, $event->getResponse());
    }

    private function createListener(): RequestListener
    {
        return new \ReflectionClass(RequestListener::class)->newInstanceWithoutConstructor();
    }
}
