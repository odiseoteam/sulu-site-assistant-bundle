<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability\Presentation;

use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\ChipMode;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;

/** Two or three options side by side on the dimensions the visitor cares about. */
final class ComparisonComponent implements CardComponent
{
    public const TOOL = 'present_comparison';

    public function __construct(private readonly ContentRecords $records)
    {
    }

    public function tool(): ToolSpec
    {
        return new ToolSpec(
            self::TOOL,
            'Compare two or three options side by side on the dimensions the visitor cares about, once they weigh finalists.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => CardSchema::title('comparison'),
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
                                'content_id' => CardSchema::contentId(),
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
        );
    }

    public function component(): PresentationComponent
    {
        return new PresentationComponent(self::TOOL, 'comparison', $this->validate(...), $this->enrich(...), $this->partial(...), ChipMode::RequiredField);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
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
    private function enrich(array $payload, EnrichmentContext $context): array
    {
        $entries = [];
        foreach (CardSchema::rows($payload['entries'] ?? null) as $entry) {
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
    private function partial(array $input, EnrichmentContext $context): array
    {
        $entries = [];
        foreach (CardSchema::rows($input['entries'] ?? null) as $entry) {
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
}
