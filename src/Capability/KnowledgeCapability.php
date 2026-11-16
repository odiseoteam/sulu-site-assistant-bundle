<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Capability\PromptFragment;
use Odiseo\AiConversationalAgentBundle\Capability\PromptSection;
use Odiseo\AiConversationalAgentBundle\Capability\ScopeArea;
use Odiseo\AiConversationalAgentBundle\Capability\ScopedCapabilityInterface;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Grounding\GroundingRule;
use Odiseo\AiConversationalAgentBundle\Grounding\Matcher;
use Odiseo\AiConversationalAgentBundle\Knowledge\KnowledgeBase;
use Odiseo\AiConversationalAgentBundle\Knowledge\KnowledgeHit;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\AiConversationalAgentBundle\Streaming\ToolOutcome;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;

/**
 * The organisation's own knowledge, beside its pages: policies, guides and answers written for
 * the assistant (Markdown files and documents edited in the admin). A question about its terms
 * goes through a read first; otherwise the model answers with what is usual, not what this
 * organisation says.
 */
final class KnowledgeCapability implements ScopedCapabilityInterface
{
    public const SEARCH = 'search_knowledge';

    private const MAX_RESULTS = 3;

    public function __construct(
        private readonly KnowledgeBase $knowledge,
        private readonly SiteAssistantConfig $config,
    ) {
    }

    public function name(): string
    {
        return 'knowledge';
    }

    public function scopeArea(): ScopeArea
    {
        return new ScopeArea('its terms and ways of working', 10);
    }

    public function tools(): array
    {
        return [new ToolSpec(
            self::SEARCH,
            "Search the organisation's own policies, guides and answers that are not pages of the site: terms, ways of working, what it does and does not do, frequent questions.",
            [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string', 'description' => 'The term or topic to look up.']],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
        )];
    }

    public function promptFragments(): array
    {
        return [new PromptFragment(
            PromptSection::HowYouWork,
            '- Answer questions about the organisation\'s terms and ways of working (what it does and does not take on, how an engagement runs, payment, guarantees, privacy) only from a search_knowledge or search_content result in this conversation, even as an aside; a saved memory or your own knowledge does not count. When nothing covers the question, say the organisation has not published that and offer to put the visitor in touch.',
            priority: 30,
        )];
    }

    public function groundingRules(): array
    {
        return [new GroundingRule(
            'knowledge',
            self::SEARCH,
            fn (string $text, TurnState $state): ?array => Matcher::matchesTermsAndCues($text, $this->config->knowledgeTerms, $this->config->knowledgeCues)
                ? ['query' => $text]
                : null,
            static fn (array $input): string => \sprintf('The organisation\'s knowledge matching this question, fetched by the host (the same data a %s call returns):', self::SEARCH),
        )];
    }

    public function components(): array
    {
        return [];
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        if (self::SEARCH !== $tool) {
            return ToolOutcome::error(\sprintf('Unknown tool: %s', $tool));
        }

        $query = $context->sanitize($input['query'] ?? '', 200);
        $hits = $this->knowledge->search($query, self::MAX_RESULTS);

        return new ToolOutcome($context->fenced([
            'query' => $query,
            'results' => \array_map(static fn (KnowledgeHit $hit): array => \array_filter([
                'id' => $hit->document->id,
                'title' => $hit->document->title,
                'url' => $hit->document->url,
                'content' => $hit->content,
            ], static fn (?string $value): bool => null !== $value), $hits),
            'result_count' => \count($hits),
        ]));
    }
}
