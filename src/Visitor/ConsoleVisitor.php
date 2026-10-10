<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Visitor;

use Odiseo\AiConversationalAgentBundle\Host\ConsoleEnvironment;
use Odiseo\SuluSiteAssistantBundle\Site\SiteLocales;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** `agent:chat` talks as a visitor on the site's default locale, like the widget would. */
final class ConsoleVisitor implements ConsoleEnvironment
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SiteLocales $site,
    ) {
    }

    public function prepare(): void
    {
        $request = Request::create('/');
        $request->headers->set(VisitorContext::LOCALE_HEADER, $this->site->defaultLocale());
        $this->requestStack->push($request);
    }
}
