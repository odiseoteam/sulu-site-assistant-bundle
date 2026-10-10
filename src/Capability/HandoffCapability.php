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
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationActivity;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;

/**
 * Where the conversation ends: a card that hands the visitor to a person with what the assistant
 * understood. The ways to get in touch come from the configuration, never from the model.
 */
final class HandoffCapability implements Capability
{
    public const TOOL = 'present_handoff';

    public function __construct(
        private readonly SiteAssistantConfig $config,
        private readonly ContentIndexInterface $index,
        private readonly PageUrlResolver $urls,
        private readonly VisitorContext $visitor,
        private readonly ContentRecords $records,
        private readonly ConversationActivity $activity,
    ) {
    }

    public function name(): string
    {
        return 'handoff';
    }

    public function tools(): array
    {
        return [new ToolSpec(
            self::TOOL,
            'Hand the visitor to a person, with a summary of what they are after. Use it once the conversation has enough to brief someone — what they need, for what, and any constraint they stated — or as soon as they ask for a person, a price, a quote or a date. Once used for a need, do not call it again for a detail added to that same need.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 60, 'description' => "Heading of the card, in the visitor's language."],
                    'summary' => ['type' => 'string', 'maxLength' => 400, 'description' => 'What this person needs and why, as you would brief a colleague. No contact details.'],
                    'content_ids' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string'], 'description' => 'The pages the conversation settled on, if any.'],
                    'note' => ['type' => 'string', 'maxLength' => 200, 'description' => 'One line to the visitor about what happens next. Optional.'],
                ],
                'required' => ['title', 'summary'],
                'additionalProperties' => false,
            ],
            wantsStatusLine: false,
        )];
    }

    public function promptFragments(): array
    {
        return [new PromptFragment(
            PromptSection::HowYouWork,
            \sprintf(
                '- You do not quote a price, commit to a date, or accept a piece of work. When the conversation reaches any of those, or the visitor asks for a person, call %1$s with what you have understood so far. Do not ask for their email, phone number or any other contact detail: the card carries the ways to get in touch, and they choose one.'
                . "\n- Call %1\$s once per need. A detail added after it updates the same handoff: say in a sentence that it has been passed along, without calling %1\$s again.",
                self::TOOL,
            ),
            priority: 30,
        )];
    }

    public function groundingRules(): array
    {
        return [];
    }

    public function components(): array
    {
        return [new PresentationComponent(
            self::TOOL,
            'handoff',
            static fn (array $input): array => [
                'title' => PayloadGuard::text($input['title'] ?? null, 60, 'title'),
                'summary' => PayloadGuard::text($input['summary'] ?? null, 400, 'summary'),
                'note' => PayloadGuard::optionalText($input['note'] ?? null, 200),
                'content_ids' => PayloadGuard::textList($input['content_ids'] ?? null, 3, 200),
            ],
            $this->enrich(...),
        )];
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        return ToolOutcome::error(\sprintf('Unknown tool: %s', $tool));
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

        $this->activity->handoffShown($context->tools->session->sessionId);

        return \array_filter([
            'title' => $payload['title'],
            'summary' => $payload['summary'],
            'note' => $payload['note'] ?? null,
            'pages' => $pages,
            'routes' => $this->routes(),
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);
    }

    /** @return list<array{kind: string, href: string}> */
    private function routes(): array
    {
        $routes = [];
        $locale = $this->visitor->locale();

        if (null !== $this->config->contactPage) {
            $page = $this->index->get(ContentDocument::identifier('pages', $this->config->contactPage, $locale));
            if (null !== $page) {
                $routes[] = ['kind' => 'page', 'href' => $this->urls->path($page->url, $locale, $page->webspaces[0] ?? null)];
            }
        }
        if (null !== $this->config->contactEmail) {
            $routes[] = ['kind' => 'email', 'href' => 'mailto:' . $this->config->contactEmail];
        }
        if (null !== $this->config->bookingUrl) {
            $routes[] = ['kind' => 'booking', 'href' => $this->config->bookingUrl];
        }

        return $routes;
    }
}
