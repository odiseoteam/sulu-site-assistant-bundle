<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Visitor;

use Odiseo\AiConversationalAgentBundle\Host\ConsoleEnvironmentInterface;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * `agent:chat` and `agent:eval` talk as a visitor on the default webspace and its default
 * locale, as the widget would. A case may name another locale or page before it runs.
 */
final class ConsoleVisitor implements ConsoleEnvironmentInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Webspaces $webspaces,
    ) {
    }

    public function prepare(?string $locale = null, ?string $webspace = null): void
    {
        $webspace = $this->webspaces->has($webspace) ? $webspace : $this->webspaces->default();
        $request = Request::create('/');
        if (null !== $webspace) {
            $request->headers->set(CurrentWebspace::HEADER, $webspace);
        }
        $request->headers->set(VisitorContext::LOCALE_HEADER, $locale ?? $this->webspaces->defaultLocale($webspace));

        while (null !== $this->requestStack->pop()) {
        }
        $this->requestStack->push($request);
    }
}
