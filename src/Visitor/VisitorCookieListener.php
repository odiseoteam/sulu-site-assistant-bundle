<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Visitor;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/** Sends the visitor cookie minted during the request. */
final class VisitorCookieListener
{
    private const LIFETIME = '+1 year';

    public function __construct(
        private readonly VisitorContext $visitor,
        private readonly string $path,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $event->getRequest()->attributes->get(VisitorContext::NEW_TOKEN_ATTRIBUTE);
        if (!\is_string($token)) {
            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(VisitorContext::COOKIE)
            ->withValue($this->visitor->sign($token))
            ->withExpires(new \DateTimeImmutable(self::LIFETIME))
            ->withPath($this->path)
            ->withSecure($event->getRequest()->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }
}
