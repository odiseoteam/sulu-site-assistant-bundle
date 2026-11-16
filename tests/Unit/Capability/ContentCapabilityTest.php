<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Capability;

use Odiseo\AiConversationalAgentBundle\Session\Provenance;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\InMemoryContentIndex;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use PHPUnit\Framework\TestCase;

final class ContentCapabilityTest extends TestCase
{
    private InMemoryContentIndex $index;

    protected function setUp(): void
    {
        $this->index = (new InMemoryContentIndex())->add(
            Site::page('p1', 'Sylius Development', 'service', 'en', 'Marketplaces on Sylius'),
            Site::page('p2', 'Desarrollo Sylius', 'service', 'es', 'Marketplaces con Sylius'),
            Site::page('c1', 'Racing Hub', 'case-study', 'en', 'A Sylius store'),
        );
    }

    public function testSearchUsesThePageLanguageAndRemembersWhatItReturned(): void
    {
        $state = new TurnState();
        $outcome = $this->capability('es')->execute(ContentCapability::SEARCH, ['query' => 'marketplaces sylius'], Site::context($state));

        self::assertFalse($outcome->refused());
        self::assertStringContainsString('Desarrollo Sylius', $outcome->resultText);
        self::assertStringContainsString('"url":"/es/desarrollo-sylius"', $outcome->resultText);
        self::assertStringNotContainsString('Sylius Development', $outcome->resultText);
        self::assertNotNull(Provenance::find($state, 'pages__p2__es', ContentRecords::KIND));
    }

    public function testATypeNarrowsTheSearchToItsTemplates(): void
    {
        $this->capability()->execute(ContentCapability::SEARCH, ['query' => 'sylius', 'type' => 'case'], Site::context());

        self::assertSame(['case-study'], $this->index->searches[0]['templates']);
    }

    public function testReadingAPageReturnsItsSections(): void
    {
        $state = new TurnState();
        $outcome = $this->capability()->execute(ContentCapability::READ, ['content_id' => 'pages__p1__en'], Site::context($state));

        self::assertStringContainsString('More about Sylius Development', $outcome->resultText);
        self::assertTrue(Provenance::hasSeen($state, 'pages__p1__en'));
    }

    public function testAPageOfAnotherWebspaceIsNotRead(): void
    {
        $other = Site::page('o1', 'Elsewhere', 'service');
        $this->index->add(new \Odiseo\SuluSiteAssistantBundle\Content\ContentDocument($other->id, 'pages', 'o1', 'en', ['other'], 'service', 'Elsewhere', '', '/elsewhere', $other->sections));

        self::assertTrue($this->capability()->execute(ContentCapability::READ, ['content_id' => 'pages__o1__en'], Site::context())->isError);
    }

    public function testItAddsTheSiteToWhatTheAssistantStaysWithin(): void
    {
        self::assertSame('what the organisation offers and publishes', $this->capability()->scopeArea()->text);
    }

    public function testAnUnknownPageIsAnError(): void
    {
        self::assertTrue($this->capability()->execute(ContentCapability::READ, ['content_id' => 'pages__nope__en'], Site::context())->isError);
    }

    public function testTheLexiconForcesASearch(): void
    {
        [$rule] = $this->capability()->groundingRules();

        self::assertSame(['query' => 'do you build marketplaces?'], $rule->fires('do you build marketplaces?', new TurnState()));
        self::assertNull($rule->fires('hello there', new TurnState()));
    }

    private function capability(string $locale = 'en'): ContentCapability
    {
        return new ContentCapability(
            $this->index,
            Site::urls($this),
            Site::visitor($this, $locale),
            Site::locales($this),
            new SiteAssistantConfig(
                contentTypes: ['service' => ['service'], 'case' => ['case-study']],
                contentTerms: ['marketplaces'],
                contentCues: ['?'],
            ),
        );
    }
}
