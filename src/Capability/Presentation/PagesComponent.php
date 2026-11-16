<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability\Presentation;

use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\ChipMode;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;

/** A shortlist of pages as cards: services, products, cases or posts, the recommended one first. */
final class PagesComponent implements CardComponent
{
    public const TOOL = 'present_pages';

    private const MAX_PICKS = 6;

    public function __construct(private readonly ContentRecords $records)
    {
    }

    public function tool(): ToolSpec
    {
        return new ToolSpec(
            self::TOOL,
            'Show a shortlist of pages as cards: services, products, cases or posts that answer the request, the one you recommend first. A single page is a link in your text, not a card.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => CardSchema::title('shortlist'),
                    'picks' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => self::MAX_PICKS,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'content_id' => CardSchema::contentId(),
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
        );
    }

    public function component(): PresentationComponent
    {
        return new PresentationComponent(self::TOOL, 'pages', $this->validate(...), $this->enrich(...), $this->partial(...), ChipMode::RequiredField);
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
            'picks' => PayloadGuard::rows($input['picks'] ?? null, 'picks', self::MAX_PICKS),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrich(array $payload, EnrichmentContext $context): array
    {
        $picks = [];
        foreach (CardSchema::rows($payload['picks'] ?? null) as $pick) {
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
    private function partial(array $input, EnrichmentContext $context): array
    {
        $picks = [];
        foreach (CardSchema::rows($input['picks'] ?? null) as $pick) {
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
}
