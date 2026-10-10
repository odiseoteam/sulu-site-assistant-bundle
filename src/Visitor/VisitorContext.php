<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Visitor;

use Odiseo\AiConversationalAgentBundle\Host\PrincipalResolver;
use Odiseo\SuluSiteAssistantBundle\Site\SiteLocales;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Who is chatting and in which language. A website has no sign-in, so the visitor is a random
 * token in a signed cookie scoped to the agent's routes: no PHP session, so pages stay
 * cacheable and any container can serve /agent/*. VisitorCookieListener sets a new one.
 */
final class VisitorContext implements PrincipalResolver
{
    public const COOKIE = 'sa_visitor';
    public const LOCALE_HEADER = 'X-Agent-Locale';
    public const NEW_TOKEN_ATTRIBUTE = '_sa_visitor_token';

    private const CONSOLE_PRINCIPAL = 'visitor-console';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SiteLocales $site,
        private readonly string $secret,
    ) {
    }

    public function principalId(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return self::CONSOLE_PRINCIPAL;
        }

        return 'visitor-' . $this->token($request);
    }

    public function isGuest(): bool
    {
        return true;
    }

    /** The locale the widget reports for its page, if the webspace has it. */
    public function locale(): string
    {
        $request = $this->requestStack->getMainRequest();
        $asked = $request?->headers->get(self::LOCALE_HEADER);

        return \is_string($asked) && \in_array($asked, $this->site->locales(), true) ? $asked : $this->site->defaultLocale();
    }

    public function sign(string $token): string
    {
        return $token . '.' . \substr(\hash_hmac('sha256', $token, $this->secret), 0, 32);
    }

    public function verify(string $cookie): ?string
    {
        $token = \strstr($cookie, '.', true);

        return false !== $token && '' !== $token && \hash_equals($this->sign($token), $cookie) ? $token : null;
    }

    private function token(Request $request): string
    {
        $token = $request->attributes->get(self::NEW_TOKEN_ATTRIBUTE);
        if (\is_string($token)) {
            return $token;
        }

        $cookie = $request->cookies->get(self::COOKIE);
        $token = \is_string($cookie) ? $this->verify($cookie) : null;
        if (null === $token) {
            $token = \bin2hex(\random_bytes(12));
            $request->attributes->set(self::NEW_TOKEN_ATTRIBUTE, $token);
        }

        return $token;
    }
}
