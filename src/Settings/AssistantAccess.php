<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Restricted access, for a launch: while it is on, the assistant is open only to a browser that
 * came in with the token. The grant is a signed cookie bound to the token, so a new token closes
 * every earlier grant; an empty token keeps it closed for everyone. No PHP session is involved,
 * so pages stay cacheable: a second, readable cookie tells the widget to show itself.
 */
final class AssistantAccess
{
    public const COOKIE = 'sa_access';

    public const UI_COOKIE = 'sa_access_ui';

    private const LIFETIME = '+30 days';

    public function __construct(
        private readonly RequestStack $requests,
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly bool $restricted,
        #[\SensitiveParameter]
        private readonly string $token,
        private readonly string $queryParameter,
    ) {
    }

    public function restricted(): bool
    {
        return $this->restricted;
    }

    public function queryParameter(): string
    {
        return $this->queryParameter;
    }

    public function matches(mixed $value): bool
    {
        return '' !== $this->token && \is_string($value) && \hash_equals($this->token, $value);
    }

    /** @return list<Cookie> the cookies that open the assistant for this browser */
    public function grant(Request $request): array
    {
        $expires = new \DateTimeImmutable(self::LIFETIME);

        return [
            Cookie::create(self::COOKIE, $this->grantValue(), $expires, '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX),
            Cookie::create(self::UI_COOKIE, '1', $expires, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX),
        ];
    }

    public function isOpen(?Request $request = null): bool
    {
        if (!$this->restricted) {
            return true;
        }

        $request ??= $this->requests->getMainRequest();
        $grant = $request?->cookies->get(self::COOKIE);

        return '' !== $this->token && \is_string($grant) && \hash_equals($this->grantValue(), $grant);
    }

    private function grantValue(): string
    {
        return \hash_hmac('sha256', 'site-assistant-access|' . $this->token, $this->secret);
    }
}
