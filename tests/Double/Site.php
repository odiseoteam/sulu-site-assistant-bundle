<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Double;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Capability\Limits;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Fencing\Fence;
use Odiseo\AiConversationalAgentBundle\Session\CurrentSession;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentSection;
use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** A two-locale webspace, a request from a visitor and the pieces a capability needs. */
final class Site
{
    public static function webspaces(TestCase $test): WebspaceManagerInterface
    {
        $webspace = new Webspace();
        $webspace->setKey('site');
        $en = new Localization('en');
        $en->setDefault(true);
        $webspace->addLocalization($en);
        $webspace->addLocalization(new Localization('es'));
        $webspace->setDefaultLocalization($en);

        /** @var WebspaceManagerInterface&\PHPUnit\Framework\MockObject\Stub $manager */
        $manager = (new \ReflectionMethod($test, 'createStub'))->invoke($test, WebspaceManagerInterface::class);
        $manager->method('findWebspaceByKey')->willReturnCallback(static fn (?string $key): ?Webspace => 'site' === $key ? $webspace : null);
        $collection = new \Sulu\Component\Webspace\Manager\WebspaceCollection(['site' => $webspace]);
        $manager->method('getWebspaceCollection')->willReturn($collection);
        $manager->method('findUrlByResourceLocator')->willReturnCallback(
            static fn (?string $slug, ?string $env, string $locale): string => 'https://example.test/' . $locale . $slug,
        );

        return $manager;
    }

    public static function locales(TestCase $test): Webspaces
    {
        return new Webspaces(self::webspaces($test), 'site');
    }

    public static function current(TestCase $test, RequestStack $stack): CurrentWebspace
    {
        /** @var EntityManagerInterface $em */
        $em = (new \ReflectionMethod($test, 'createStub'))->invoke($test, EntityManagerInterface::class);

        return new CurrentWebspace(self::locales($test), $stack, new CurrentSession(), $em, Conversation::class);
    }

    public static function urls(TestCase $test): PageUrlResolver
    {
        return new PageUrlResolver(self::webspaces($test), 'test');
    }

    public static function visitor(TestCase $test, string $locale = 'en', ?Request $request = null): VisitorContext
    {
        $request ??= Request::create('/agent/chat', 'POST');
        $request->headers->set(VisitorContext::LOCALE_HEADER, $locale);
        $stack = new RequestStack();
        $stack->push($request);

        return new VisitorContext($stack, self::locales($test), self::current($test, $stack), 'secret');
    }

    public static function context(?TurnState $state = null): ToolContext
    {
        return new ToolContext(
            new SessionContext('session-1', 'visitor-abc'),
            $state ?? new TurnState(),
            new Fence('site_data', 'Quoted site content.'),
            new Limits(),
        );
    }

    public static function page(string $id, string $title, string $templateKey, string $locale = 'en', string $text = ''): ContentDocument
    {
        return new ContentDocument(
            ContentDocument::identifier('pages', $id, $locale),
            'pages',
            $id,
            $locale,
            ['site'],
            $templateKey,
            $title,
            'Summary of ' . $title,
            '/' . \strtolower(\str_replace(' ', '-', $title)),
            [new ContentSection(null, $text ?: $title), new ContentSection('Details', 'More about ' . $title)],
        );
    }
}
