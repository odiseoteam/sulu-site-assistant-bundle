<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Capability\Capability;
use Odiseo\AiConversationalAgentBundle\Capability\PromptFragment;
use Odiseo\AiConversationalAgentBundle\Capability\PromptSection;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\AiConversationalAgentBundle\Streaming\ToolOutcome;

/**
 * Page cards: a shortlist and a comparison. The model picks content ids and writes the reasons;
 * title, link, summary and image come from what the session read.
 */
final class PresentationCapability implements Capability
{
    public const PAGES = 'present_pages';
    public const COMPARISON = 'present_comparison';

    public function __construct(private readonly ContentRecords $records)
    {
    }

    public function name(): string
    {
        return 'presentation';
    }

    public function tools(): array
    {
        $contentId = ['type' => 'string', 'description' => 'content_id returned by a tool this session.'];
        $title = static fn (string $what): array => ['type' => 'string', 'maxLength' => 80, 'description' => \sprintf('Short heading for the %s.', $what)];

        return [
            new ToolSpec(
                self::PAGES,
                'Show a shortlist of pages as cards: services, products, cases or posts that answer the request, the one you recommend first.',
                [
                    'type' => 'object',
                    'properties' => [
                        'title' => $title('shortlist'),
                        'picks' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'maxItems' => 6,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'content_id' => $contentId,
                                    'reason' => ['type' => 'string', 'maxLength' => 160, 'description' => 'One clause tying the page to what the visitor said.'],
                                ],
                                'required' => ['content_id'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['picks'],
                    'additionalProperties' => false,
                ],
                wantsStatusLine: false,
            ),
            new ToolSpec(
                self::COMPARISON,
                'Compare two or three options side by side on the dimensions the visitor cares about.',
                [
                    'type' => 'object',
                    'properties' => [
                        'title' => $title('comparison'),
                        'dimensions' => [
                            'type' => 'array',
                            'maxItems' => 6,
                            'items' => ['type' => 'string', 'maxLength' => 60],
                            'description' => 'What the options are compared on, in the visitor\'s words.',
                        ],
                        'entries' => [
                            'type' => 'array',
                            'minItems' => 2,
                            'maxItems' => 3,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'content_id' => $contentId,
                                    'pros' => ['type' => 'array', 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 120], 'description' => 'Strengths, from what the pages say.'],
                                    'cons' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string', 'maxLength' => 120], 'description' => 'Limits, from what the pages say.'],
                                    'best_for' => ['type' => 'string', 'maxLength' => 80, 'description' => 'Who or what this option suits best.'],
                                ],
                                'required' => ['content_id'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'recommended_content_id' => ['type' => 'string', 'description' => 'The entry you recommend, if one clearly fits.'],
                    ],
                    'required' => ['entries'],
                    'additionalProperties' => false,
                ],
                wantsStatusLine: false,
            ),
        ];
    }

    public function promptFragments(): array
    {
        return [new PromptFragment(
            PromptSection::Presentation,
            '- A card names pages by content_id and nothing else: title, link, summary and image are joined from what the session retrieved. Your own words go in the reasons, pros and cons and headings, in the visitor\'s language. Use present_pages for two or more options and present_comparison once the visitor weighs finalists; a single page is a link in your text, not a card.',
            priority: 10,
        )];
    }

    public function groundingRules(): array
    {
        return [];
    }

    public function components(): array
    {
        return [
            new PresentationComponent(self::PAGES, 'pages', $this->validatePages(...), $this->enrichPages(...), $this->partialPages(...)),
            new PresentationComponent(self::COMPARISON, 'comparison', $this->validateComparison(...), $this->enrichComparison(...), $this->partialComparison(...)),
        ];
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        return ToolOutcome::error(\sprintf('Unknown tool: %s', $tool));
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validatePages(array $input): array
    {
        return [
            'title' => PayloadGuard::optionalText($input['title'] ?? null, 80),
            'picks' => PayloadGuard::rows($input['picks'] ?? null, 'picks', 6),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrichPages(array $payload, EnrichmentContext $context): array
    {
        $picks = [];
        foreach (self::rows($payload['picks'] ?? null) as $pick) {
            $record = ContentRecords::resolve($context, $pick['content_id'] ?? null);
            if (null !== $record) {
                $picks[] = $this->records->card($record) + \array_filter(['reason' => PayloadGuard::optionalText($pick['reason'] ?? null, 160)]);
            }
        }

        return \array_filter([
            'title' => $payload['title'] ?? null,
            'picks' => PayloadGuard::requireAny($picks, 'shortlist'),
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function partialPages(array $input, EnrichmentContext $context): array
    {
        $picks = [];
        foreach (self::rows($input['picks'] ?? null) as $pick) {
            $record = ContentRecords::peek($context, $pick['content_id'] ?? null);
            if (null !== $record) {
                $picks[] = $this->records->card($record) + \array_filter(['reason' => PayloadGuard::optionalText($pick['reason'] ?? null, 160)]);
            }
        }

        return \array_filter([
            'title' => PayloadGuard::optionalText($input['title'] ?? null, 80),
            'picks' => $picks,
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validateComparison(array $input): array
    {
        return [
            'title' => PayloadGuard::optionalText($input['title'] ?? null, 80),
            'dimensions' => PayloadGuard::textList($input['dimensions'] ?? null, 6, 60),
            'entries' => PayloadGuard::rows($input['entries'] ?? null, 'entries', 3, 2),
            'recommended_content_id' => PayloadGuard::optionalText($input['recommended_content_id'] ?? null, 200),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrichComparison(array $payload, EnrichmentContext $context): array
    {
        $entries = [];
        foreach (self::rows($payload['entries'] ?? null) as $entry) {
            $record = ContentRecords::resolve($context, $entry['content_id'] ?? null);
            if (null !== $record) {
                $entries[] = $this->entry($entry, $this->records->card($record));
            }
        }
        $entries = PayloadGuard::requireAny($entries, 'comparison');
        if (\count($entries) < 2) {
            $context->note('Only one entry survived provenance; the comparison shows it alone.');
        }

        $recommended = \is_string($payload['recommended_content_id'] ?? null) ? $payload['recommended_content_id'] : null;
        if (null !== $recommended && !\in_array($recommended, \array_column($entries, 'content_id'), true)) {
            $context->note(\sprintf('recommended_content_id %s is not among the entries shown; it was dropped.', $recommended));
            $recommended = null;
        }

        return \array_filter([
            'title' => $payload['title'] ?? null,
            'dimensions' => [] !== ($payload['dimensions'] ?? []) ? $payload['dimensions'] : null,
            'entries' => $entries,
            'recommended_content_id' => $recommended,
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function partialComparison(array $input, EnrichmentContext $context): array
    {
        $entries = [];
        foreach (self::rows($input['entries'] ?? null) as $entry) {
            $record = ContentRecords::peek($context, $entry['content_id'] ?? null);
            if (null !== $record) {
                $entries[] = $this->entry($entry, $this->records->card($record));
            }
        }
        $dimensions = PayloadGuard::textList($input['dimensions'] ?? null, 6, 60);

        return \array_filter([
            'title' => PayloadGuard::optionalText($input['title'] ?? null, 80),
            'dimensions' => [] !== $dimensions ? $dimensions : null,
            'entries' => $entries,
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed>
     */
    private function entry(array $entry, array $card): array
    {
        return $card + \array_filter([
            'pros' => PayloadGuard::textList($entry['pros'] ?? null, 4, 120),
            'cons' => PayloadGuard::textList($entry['cons'] ?? null, 3, 120),
            'best_for' => PayloadGuard::optionalText($entry['best_for'] ?? null, 80),
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $value): array
    {
        /** @var list<array<string, mixed>> */
        return \is_array($value) ? \array_values(\array_filter($value, 'is_array')) : [];
    }
}
