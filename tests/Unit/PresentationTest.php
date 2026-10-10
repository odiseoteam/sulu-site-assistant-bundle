<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationRefused;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Capability\HandoffCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\PresentationCapability;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationActivity;
use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Site\MediaUrlResolver;
use Odiseo\SuluSiteAssistantBundle\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\InMemoryContentIndex;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

final class PresentationTest extends TestCase
{
    public function testACardOnlyShowsPagesTheSessionRead(): void
    {
        $state = new TurnState();
        ContentRecords::remember($state, Site::page('p1', 'Sylius Development', 'service'), 'service', '/en/sylius');
        $context = new EnrichmentContext(Site::context($state));

        [$pages] = (new PresentationCapability($this->records()))->components();
        $payload = self::enrich($pages, ($pages->validate)(['picks' => [
            ['content_id' => 'pages__p1__en', 'reason' => 'Fits the marketplace.'],
            ['content_id' => 'pages__invented__en'],
        ]]), $context);

        self::assertCount(1, $payload['picks']);
        self::assertSame(['content_id' => 'pages__p1__en', 'title' => 'Sylius Development', 'type' => 'service', 'url' => '/en/sylius', 'summary' => 'Summary of Sylius Development', 'reason' => 'Fits the marketplace.'], $payload['picks'][0]);
        self::assertCount(1, $context->notes());
    }

    public function testACardWithNothingSeenIsRefused(): void
    {
        [$pages] = (new PresentationCapability($this->records()))->components();

        $this->expectException(PresentationRefused::class);
        self::enrich($pages, ['picks' => [['content_id' => 'pages__x__en']]], new EnrichmentContext(Site::context()));
    }

    public function testTheHandoffCarriesTheConfiguredRoutesOnly(): void
    {
        $index = (new InMemoryContentIndex())->add(Site::page('contact', 'Contact', 'contact', 'es'));
        $handoff = new HandoffCapability(
            new SiteAssistantConfig(contactPage: 'contact', contactEmail: 'team@example.test'),
            $index,
            Site::urls($this),
            Site::visitor($this, 'es'),
            $this->records(),
            new ConversationActivity($this->createStub(EntityManagerInterface::class), new NullLogger(), Conversation::class),
        );

        [$component] = $handoff->components();
        $payload = self::enrich($component, ($component->validate)(['title' => 'Hablemos', 'summary' => 'Quiere un marketplace.']), new EnrichmentContext(Site::context()));

        self::assertSame([
            ['kind' => 'page', 'href' => '/es/contact'],
            ['kind' => 'email', 'href' => 'mailto:team@example.test'],
        ], $payload['routes']);
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

    private function records(): ContentRecords
    {
        return new ContentRecords(new MediaUrlResolver($this->createStub(MediaManagerInterface::class), 'sulu-400x400'));
    }
}
