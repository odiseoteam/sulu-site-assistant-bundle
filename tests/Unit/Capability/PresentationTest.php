<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Capability;

use Odiseo\AiConversationalAgentBundle\Presentation\ChipMode;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationRefusedException;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Capability\PresentationCapability;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Site\MediaUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

final class PresentationTest extends TestCase
{
    public function testTheToolsComeInAFixedOrder(): void
    {
        $tools = \array_map(static fn ($tool): string => $tool->name, $this->capability()->tools());

        self::assertSame(['present_pages', 'present_comparison', 'present_guide'], $tools);
    }

    public function testEachCardCarriesItsChipNote(): void
    {
        $components = $this->components();

        self::assertSame(SiteAssistantConfig::CHIP_NOTES['pages'], $components['present_pages']->chipNote);
        self::assertSame(ChipMode::RequiredField, $components['present_pages']->chips);
        self::assertSame(ChipMode::Field, $components['present_guide']->chips);
    }

    public function testACardOnlyShowsPagesTheSessionRead(): void
    {
        $context = new EnrichmentContext(Site::context($this->seen()));
        $pages = $this->components()['present_pages'];

        $payload = self::enrich($pages, ($pages->validate)(['picks' => [
            ['content_id' => 'pages__p1__en', 'reason' => 'Fits the marketplace.'],
            ['content_id' => 'pages__invented__en'],
        ]]), $context);

        self::assertIsArray($payload['picks']);
        self::assertCount(1, $payload['picks']);
        self::assertSame(['content_id' => 'pages__p1__en', 'title' => 'Sylius Development', 'type' => 'service', 'url' => '/en/sylius', 'summary' => 'Summary of Sylius Development', 'reason' => 'Fits the marketplace.'], $payload['picks'][0]);
        self::assertCount(1, $context->notes(), 'the invented page is reported to the model');
    }

    public function testACardWithNothingSeenIsRefused(): void
    {
        $pages = $this->components()['present_pages'];

        $this->expectException(PresentationRefusedException::class);
        self::enrich($pages, ['picks' => [['content_id' => 'pages__x__en']]], new EnrichmentContext(Site::context()));
    }

    public function testAComparisonDropsARecommendationItDoesNotShow(): void
    {
        $context = new EnrichmentContext(Site::context($this->seen()));
        $comparison = $this->components()['present_comparison'];

        $payload = self::enrich($comparison, ($comparison->validate)([
            'entries' => [
                ['content_id' => 'pages__p1__en', 'pros' => ['Marketplaces'], 'best_for' => 'Big catalogs'],
                ['content_id' => 'pages__p2__en', 'cons' => ['Needs a team']],
            ],
            'recommended_content_id' => 'pages__elsewhere__en',
        ]), $context);

        self::assertIsArray($payload['entries']);
        self::assertCount(2, $payload['entries']);
        self::assertArrayNotHasKey('recommended_content_id', $payload);
        self::assertSame(['Marketplaces'], $payload['entries'][0]['pros']);
    }

    public function testAGuideLinksOnlyThePagesItWasDrawnFrom(): void
    {
        $context = new EnrichmentContext(Site::context($this->seen()));
        $guide = $this->components()['present_guide'];

        $payload = self::enrich($guide, ($guide->validate)([
            'title' => 'How a project starts',
            'sections' => [['heading' => 'A call', 'body' => 'We talk it over.']],
            'content_ids' => ['pages__p1__en', 'pages__made_up__en'],
        ]), $context);

        self::assertSame('How a project starts', $payload['title']);
        self::assertIsArray($payload['pages']);
        self::assertSame(['pages__p1__en'], \array_column($payload['pages'], 'content_id'));
    }

    public function testAPartialFrameSkipsWhatIsStillBeingWritten(): void
    {
        $context = new EnrichmentContext(Site::context($this->seen()));
        $pages = $this->components()['present_pages'];
        self::assertNotNull($pages->partial);

        $partial = ($pages->partial)(['picks' => [['content_id' => 'pages__p1__en'], ['content_id' => 'pages__p']]], $context);

        self::assertIsArray($partial);
        self::assertIsArray($partial['picks']);
        self::assertCount(1, $partial['picks']);
        self::assertSame([], $context->notes(), 'a half-written id is not an error yet');
    }

    /** @return array<string, PresentationComponent> */
    private function components(): array
    {
        $components = [];
        foreach ($this->capability()->components() as $component) {
            $components[$component->tool] = $component;
        }

        return $components;
    }

    private function capability(): PresentationCapability
    {
        return new PresentationCapability(new ContentRecords(new MediaUrlResolver($this->createStub(MediaManagerInterface::class), 'sulu-400x400')));
    }

    private function seen(): TurnState
    {
        $state = new TurnState();
        ContentRecords::remember($state, Site::page('p1', 'Sylius Development', 'service'), 'service', '/en/sylius');
        ContentRecords::remember($state, Site::page('p2', 'Symfony Development', 'service'), 'service', '/en/symfony');

        return $state;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function enrich(PresentationComponent $component, array $payload, EnrichmentContext $context): array
    {
        self::assertNotNull($component->enrich);

        return ($component->enrich)($payload, $context);
    }
}
