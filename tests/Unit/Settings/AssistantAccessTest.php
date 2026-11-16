<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Settings;

use Odiseo\SuluSiteAssistantBundle\EventListener\AssistantAccessListener;
use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AssistantAccessTest extends TestCase
{
    public function testUnrestrictedIsOpenToEveryone(): void
    {
        self::assertTrue($this->access(false, '')->isOpen(Request::create('/')));
    }

    public function testTheTokenGrantsACookieAndTheRedirectDropsIt(): void
    {
        $access = $this->access(true, 'launch');
        $request = Request::create('/en/services?agent=launch&utm=x');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        (new AssistantAccessListener($access))->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en/services?utm=x', $response->getTargetUrl());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }
        self::assertArrayHasKey(AssistantAccess::COOKIE, $cookies);
        self::assertTrue($cookies[AssistantAccess::COOKIE]->isHttpOnly());
        self::assertFalse($cookies[AssistantAccess::UI_COOKIE]->isHttpOnly(), 'the widget reads it');

        self::assertTrue($access->isOpen(Request::create('/', cookies: [AssistantAccess::COOKIE => (string) $cookies[AssistantAccess::COOKIE]->getValue()])));
    }

    public function testAWrongTokenIsDroppedWithoutAGrant(): void
    {
        $access = $this->access(true, 'launch');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/?agent=guess'), HttpKernelInterface::MAIN_REQUEST);

        (new AssistantAccessListener($access))->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame([], \array_filter($event->getResponse()->headers->getCookies(), static fn (Cookie $cookie): bool => AssistantAccess::COOKIE === $cookie->getName()));
    }

    public function testANewTokenClosesTheOldGrants(): void
    {
        $old = $this->access(true, 'launch')->grant(Request::create('/'))[0]->getValue();

        self::assertFalse($this->access(true, 'launch-2')->isOpen(Request::create('/', cookies: [AssistantAccess::COOKIE => (string) $old])));
        self::assertFalse($this->access(true, '')->isOpen(Request::create('/', cookies: [AssistantAccess::COOKIE => (string) $old])), 'no token keeps it closed');
    }

    private function access(bool $restricted, string $token): AssistantAccess
    {
        return new AssistantAccess(new RequestStack(), 'kernel-secret', $restricted, $token, 'agent');
    }
}
