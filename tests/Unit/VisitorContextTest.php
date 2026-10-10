<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit;

use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorCookieListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class VisitorContextTest extends TestCase
{
    public function testANewVisitorGetsATokenThatIsSetAsASignedCookie(): void
    {
        $request = Request::create('/agent/session', 'POST');
        $visitor = Site::visitor($this, 'en', $request);

        $principal = $visitor->principalId();
        self::assertStringStartsWith('visitor-', $principal);
        self::assertSame($principal, $visitor->principalId(), 'stable within the request');

        $response = new Response();
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        (new VisitorCookieListener($visitor, '/agent'))->onKernelResponse($event);

        $cookie = $response->headers->getCookies()[0];
        self::assertSame(VisitorContext::COOKIE, $cookie->getName());
        self::assertSame('/agent', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(\substr($principal, 8), $visitor->verify((string) $cookie->getValue()));
    }

    public function testAReturningVisitorKeepsTheirToken(): void
    {
        $visitor = Site::visitor($this);
        $request = Request::create('/agent/chat', 'POST', cookies: [VisitorContext::COOKIE => $visitor->sign('abc123')]);

        self::assertSame('visitor-abc123', Site::visitor($this, 'en', $request)->principalId());
    }

    public function testATamperedCookieIsReplaced(): void
    {
        $request = Request::create('/agent/chat', 'POST', cookies: [VisitorContext::COOKIE => 'abc123.forged']);

        self::assertNotSame('visitor-abc123', Site::visitor($this, 'en', $request)->principalId());
        self::assertIsString($request->attributes->get(VisitorContext::NEW_TOKEN_ATTRIBUTE));
    }

    public function testTheLocaleMustBeOneOfTheWebspace(): void
    {
        self::assertSame('es', Site::visitor($this, 'es')->locale());
        self::assertSame('en', Site::visitor($this, 'de')->locale());
    }
}
