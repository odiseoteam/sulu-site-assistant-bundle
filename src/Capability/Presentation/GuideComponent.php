<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability\Presentation;

use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\ChipMode;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;

/** How something works or how to get started, as short steps with the pages they come from. */
final class GuideComponent implements CardComponent
{
    public const TOOL = 'present_guide';

    private const MAX_SECTIONS = 8;

    private const MAX_PAGES = 4;

    public function __construct(private readonly ContentRecords $records)
    {
    }

    public function tool(): ToolSpec
    {
        return new ToolSpec(
            self::TOOL,
            'Show how something works, a process or how to get started as a card of short steps, with the pages the steps were drawn from. Every step must come from a page or a knowledge result read in this conversation.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => CardSchema::title('guide'),
                    'sections' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => self::MAX_SECTIONS,
                        'description' => 'Steps or sections of one to three sentences each, in order.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'heading' => ['type' => 'string', 'maxLength' => 80, 'description' => 'Step heading.'],
                                'body' => ['type' => 'string', 'maxLength' => 600, 'description' => 'Step text.'],
                            ],
                            'required' => ['heading', 'body'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'content_ids' => ['type' => 'array', 'items' => CardSchema::contentId(), 'maxItems' => self::MAX_PAGES, 'description' => 'The pages the steps were drawn from.'],
                ],
                'required' => ['title', 'sections'],
                'additionalProperties' => false,
            ],
            wantsStatusLine: false,
        );
    }

    public function component(): PresentationComponent
    {
        return new PresentationComponent(self::TOOL, 'guide', $this->validate(...), $this->enrich(...), $this->partial(...), ChipMode::Field);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        $sections = [];
        foreach (PayloadGuard::rows($input['sections'] ?? null, 'sections', self::MAX_SECTIONS) as $section) {
            $sections[] = [
                'heading' => PayloadGuard::text($section['heading'] ?? null, 80, 'heading'),
                'body' => PayloadGuard::text($section['body'] ?? null, 600, 'body'),
            ];
        }

        return [
            'title' => PayloadGuard::text($input['title'] ?? null, 80, 'title'),
            'sections' => $sections,
            'content_ids' => PayloadGuard::textList($input['content_ids'] ?? null, self::MAX_PAGES, 200),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrich(array $payload, EnrichmentContext $context): array
    {
        $pages = [];
        foreach (\is_array($payload['content_ids'] ?? null) ? $payload['content_ids'] : [] as $id) {
            $record = ContentRecords::resolve($context, $id);
            if (null !== $record) {
                $pages[] = $this->records->card($record);
            }
        }

        return \array_filter([
            'title' => $payload['title'],
            'sections' => $payload['sections'],
            'pages' => [] !== $pages ? $pages : null,
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|null
     */
    private function partial(array $input, EnrichmentContext $context): ?array
    {
        $sections = [];
        foreach (CardSchema::rows($input['sections'] ?? null) as $section) {
            $heading = PayloadGuard::optionalText($section['heading'] ?? null, 80);
            $body = PayloadGuard::optionalText($section['body'] ?? null, 600);
            if (null !== $heading && null !== $body) {
                $sections[] = ['heading' => $heading, 'body' => $body];
            }
        }
        $title = PayloadGuard::optionalText($input['title'] ?? null, 80);
        if (null === $title && [] === $sections) {
            return null;
        }

        return \array_filter(['title' => $title, 'sections' => $sections], static fn (mixed $v): bool => null !== $v);
    }
}
