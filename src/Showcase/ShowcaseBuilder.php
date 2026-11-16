<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Showcase;

use Odiseo\AiConversationalAgentBundle\Capability\CapabilityRegistry;
use Odiseo\AiConversationalAgentBundle\Capability\Limits;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Fencing\Fence;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationRunner;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\AiConversationalAgentBundle\Streaming\EventType;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;

/**
 * Builds the showcase cards the way a real turn does: pages are read from the index into
 * provenance, and each component is validated and enriched. Without a fixture it takes the first
 * pages the index returns for the webspace; the handoff cards come ready, since only a case
 * produces them.
 */
final class ShowcaseBuilder
{
    private const HANDOFF_CARDS = [
        ['label' => 'Handoff — the visitor writes', 'payload' => ['status' => 'registered', 'reference' => 'W-7KQ2MX', 'path' => 'visitor_writes', 'open' => true]],
        ['label' => 'Handoff — the team contacts', 'payload' => ['status' => 'registered', 'reference' => 'W-7KQ2MX', 'path' => 'team_contact', 'open' => true, 'phone_on_file' => '****4321', 'self_serve' => ['visitor_writes']]],
        ['label' => 'Handoff — out of hours', 'payload' => ['status' => 'registered', 'reference' => 'W-7KQ2MX', 'path' => 'team_contact', 'open' => false]],
        ['label' => 'Handoff — choose how to go on', 'payload' => ['status' => 'choose_path', 'reference' => 'W-7KQ2MX', 'paths' => ['visitor_writes', 'team_contact']]],
        ['label' => 'Handoff — failed', 'payload' => ['status' => 'failed', 'opening' => 'Sorry about that.']],
    ];

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly ContentIndexInterface $index,
        private readonly PageUrlResolver $urls,
        private readonly VisitorContext $visitor,
        private readonly SiteAssistantConfig $config,
        private readonly Fence $fence,
        private readonly Limits $limits,
        private readonly ?string $timezone = null,
        private readonly string $fixturePath = '',
    ) {
    }

    /** @return array{cards: list<array{label: string, component: string, payload: array<string, mixed>}>, suggestions: list<string>} */
    public function build(): array
    {
        $fixture = $this->fixture();

        $state = new TurnState();
        $ids = $this->seed($state, $fixture['pages']);
        $session = new SessionContext('showcase', 'visitor-showcase', $this->timezone ?? \date_default_timezone_get());
        $tools = new ToolContext($session, $state, $this->fence, $this->limits);
        $components = $this->capabilities->components();

        $cards = [];
        foreach ($fixture['cards'] ?? $this->defaultCards($ids) as $card) {
            $label = Scalar::string($card['label'] ?? null);
            if (isset($card['payload'])) {
                $cards[] = ['label' => $label, 'component' => Scalar::string($card['component'] ?? null), 'payload' => Scalar::keyed($card['payload'])];

                continue;
            }

            $tool = Scalar::string($card['tool'] ?? null);
            $component = $components[$tool] ?? null;
            if (null === $component) {
                continue;
            }
            $outcome = PresentationRunner::run($component, Scalar::keyed($card['input'] ?? null), $tools, '');
            foreach ($outcome->events as $event) {
                if (EventType::Ui === $event->type) {
                    /** @var array{component: string, payload: array<string, mixed>} $data */
                    $data = $event->data;
                    $cards[] = ['label' => $label, 'component' => $data['component'], 'payload' => $data['payload']];
                }
            }
        }

        if (isset($components['present_handoff'])) {
            foreach (self::HANDOFF_CARDS as $card) {
                $cards[] = ['label' => $card['label'], 'component' => 'handoff', 'payload' => $card['payload']];
            }
        }

        return ['cards' => $cards, 'suggestions' => $fixture['suggestions'] ?? ['What do you offer?', 'How do you work?', 'Can I talk to someone?']];
    }

    /** @return array{pages: list<string>, cards?: list<array<string, mixed>>, suggestions?: list<string>} */
    private function fixture(): array
    {
        if ('' === $this->fixturePath || !\is_file($this->fixturePath)) {
            return ['pages' => []];
        }

        $fixture = \json_decode((string) \file_get_contents($this->fixturePath), true, 512, \JSON_THROW_ON_ERROR);
        $fixture = Scalar::keyed($fixture);

        return \array_filter([
            'pages' => Scalar::strings($fixture['pages'] ?? null),
            'cards' => isset($fixture['cards']) ? Scalar::rows($fixture['cards']) : null,
            'suggestions' => isset($fixture['suggestions']) ? Scalar::strings($fixture['suggestions']) : null,
        ], static fn (mixed $value): bool => null !== $value) + ['pages' => []];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string> the ids read into provenance
     */
    private function seed(TurnState $state, array $ids): array
    {
        $documents = [] === $ids
            ? \array_map(static fn ($hit): ContentDocument => $hit->document, $this->index->search('', $this->visitor->locale(), $this->visitor->webspace(), [], 4))
            : \array_values(\array_filter(\array_map($this->index->get(...), $ids)));

        $seen = [];
        foreach ($documents as $document) {
            ContentRecords::remember($state, $document, $this->config->typeOf($document->templateKey), $this->urls->path($document->url, $document->locale, $document->webspaces[0] ?? null));
            $seen[] = $document->id;
        }

        return $seen;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function defaultCards(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $cards = [[
            'label' => 'Pages — shortlist',
            'tool' => 'present_pages',
            'input' => ['title' => 'Where to start', 'picks' => \array_map(static fn (string $id, int $i): array => ['content_id' => $id, 'reason' => 0 === $i ? 'Closest to what you described' : 'Worth a look if that is not quite it'], $ids, \array_keys($ids))],
        ]];
        if (\count($ids) >= 2) {
            $cards[] = [
                'label' => 'Comparison',
                'tool' => 'present_comparison',
                'input' => [
                    'title' => 'Side by side',
                    'dimensions' => ['scope', 'timing'],
                    'entries' => [
                        ['content_id' => $ids[0], 'pros' => ['Covers the whole project'], 'cons' => ['Longer to start'], 'best_for' => 'A team that wants it done end to end'],
                        ['content_id' => $ids[1], 'pros' => ['Starts small'], 'cons' => ['Needs your own team'], 'best_for' => 'Teams with in-house developers'],
                    ],
                    'recommended_content_id' => $ids[0],
                ],
            ];
        }
        $cards[] = [
            'label' => 'Guide',
            'tool' => 'present_guide',
            'input' => [
                'title' => 'How a project starts',
                'sections' => [
                    ['heading' => 'A first call', 'body' => 'We go over what you need and what you already have.'],
                    ['heading' => 'A proposal', 'body' => 'You get the scope, the approach and the next steps in writing.'],
                    ['heading' => 'Kick-off', 'body' => 'Work starts with a short plan both sides agree on.'],
                ],
                'content_ids' => \array_slice($ids, 0, 2),
            ],
        ];

        return $cards;
    }
}
