<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Capability\Capability;
use Odiseo\AiConversationalAgentBundle\Capability\PromptFragment;
use Odiseo\AiConversationalAgentBundle\Capability\PromptSection;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Grounding\GroundingRule;
use Odiseo\AiConversationalAgentBundle\Grounding\Matcher;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\AiConversationalAgentBundle\Streaming\ToolOutcome;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\SiteLocales;
use Odiseo\SuluSiteAssistantBundle\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;

/**
 * Searching and reading what the site publishes. Everything returned enters the session's
 * provenance, so cards and the handoff can only name pages the conversation has seen.
 */
final class ContentCapability implements Capability
{
    public const SEARCH = 'search_content';
    public const READ = 'get_content';

    private const READ_CHARS = 9000;

    public function __construct(
        private readonly ContentIndexInterface $index,
        private readonly PageUrlResolver $urls,
        private readonly VisitorContext $visitor,
        private readonly SiteLocales $site,
        private readonly SiteAssistantConfig $config,
    ) {
    }

    public function name(): string
    {
        return 'content';
    }

    public function tools(): array
    {
        $search = [
            'query' => ['type' => 'string', 'description' => "What to look for, in the site's own words."],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->config->maxSearchResults, 'description' => 'Maximum results.'],
        ];
        if ([] !== $this->config->contentTypes) {
            $search['type'] = ['type' => 'string', 'enum' => \array_keys($this->config->contentTypes), 'description' => 'Only pages of this kind.'];
        }
        if (\count($this->site->locales()) > 1) {
            $search['locale'] = ['type' => 'string', 'enum' => $this->site->locales(), 'description' => 'Language of the pages; the visitor\'s page language when omitted.'];
        }

        return [
            new ToolSpec(
                self::SEARCH,
                'Search the pages and articles this site publishes: what it offers, how it works, its cases and posts, its terms. Returns each match with its content_id, title, type, link, summary and the passage that matched. Run one search per distinct thing the request names.',
                [
                    'type' => 'object',
                    'properties' => $search,
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ),
            new ToolSpec(
                self::READ,
                'Read one page in full, section by section. Use it before describing a page in detail, before comparing options, and whenever a summary does not settle the question.',
                [
                    'type' => 'object',
                    'properties' => [
                        'content_id' => ['type' => 'string', 'description' => 'content_id returned by search_content.'],
                    ],
                    'required' => ['content_id'],
                    'additionalProperties' => false,
                ],
            ),
        ];
    }

    public function promptFragments(): array
    {
        $fragments = [
            new PromptFragment(
                PromptSection::HowYouWork,
                '- Ground every statement about what this organisation offers, how it works, what it has done and its terms in a search_content or get_content result from this conversation. When the site does not cover something, say it has not published that and offer to put the visitor in touch; do not fill the gap from general knowledge.',
                priority: 10,
            ),
            new PromptFragment(
                PromptSection::Tools,
                '- Link to a page only with the url a tool returned for it, as a markdown link. Never compose a url.',
                priority: 10,
            ),
        ];
        if ([] !== $this->config->contentTypes) {
            $fragments[] = new PromptFragment(
                PromptSection::Tools,
                \sprintf('- Pages come in these kinds: %s. Pass one as type when the visitor asks for that kind.', \implode(', ', \array_keys($this->config->contentTypes))),
                priority: 11,
            );
        }
        if ('' !== $this->config->searchNotes) {
            $fragments[] = new PromptFragment(PromptSection::Tools, '- ' . $this->config->searchNotes, priority: 12);
        }

        return $fragments;
    }

    public function groundingRules(): array
    {
        return [new GroundingRule(
            'content',
            self::SEARCH,
            fn (string $text, TurnState $state): ?array => Matcher::matchesTermsAndCues($text, $this->config->groundingTerms, $this->config->groundingCues)
                ? ['query' => $text]
                : null,
            static fn (array $input): string => \sprintf('Site pages matching this question, fetched by the host (the same data a %s call returns):', self::SEARCH),
        )];
    }

    public function components(): array
    {
        return [];
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        return match ($tool) {
            self::SEARCH => $this->search($input, $context),
            self::READ => $this->read($input, $context),
            default => ToolOutcome::error(\sprintf('Unknown tool: %s', $tool)),
        };
    }

    /** @param array<string, mixed> $input */
    private function search(array $input, ToolContext $context): ToolOutcome
    {
        $query = $context->sanitize($input['query'] ?? '', 300);
        $type = \is_string($input['type'] ?? null) ? $input['type'] : null;
        $locale = \is_string($input['locale'] ?? null) && \in_array($input['locale'], $this->site->locales(), true) ? $input['locale'] : $this->visitor->locale();
        $ceiling = \min($this->config->maxSearchResults, $context->limits->maxResultsPerCall);
        $limit = $context->clampLimit($input['limit'] ?? null, $ceiling, $ceiling);

        $hits = $this->index->search($query, $locale, $this->site->webspace(), $this->config->templatesOf($type), $limit);

        $results = [];
        foreach ($hits as $hit) {
            $results[] = $this->remember($context, $hit->document) + \array_filter(['passage' => $hit->passage]);
        }

        return new ToolOutcome($context->fenced([
            'query' => $query,
            'results' => $results,
            'result_count' => \count($results),
        ]));
    }

    /** @param array<string, mixed> $input */
    private function read(array $input, ToolContext $context): ToolOutcome
    {
        $id = $context->sanitize($input['content_id'] ?? '', 200);
        $document = '' === $id ? null : $this->index->get($id);
        if (null === $document) {
            return ToolOutcome::error(\sprintf('No page with content_id %s. Find it with search_content first.', $id));
        }

        $sections = [];
        $budget = self::READ_CHARS;
        foreach ($document->sections as $section) {
            if ($budget <= 0) {
                break;
            }
            $text = \mb_substr($section->text, 0, $budget);
            $budget -= \mb_strlen($text);
            $sections[] = \array_filter(['heading' => $section->heading, 'text' => $text], static fn (?string $v): bool => null !== $v);
        }

        return new ToolOutcome($context->fenced($this->remember($context, $document) + ['sections' => $sections]));
    }

    /** @return array<string, mixed> */
    private function remember(ToolContext $context, ContentDocument $document): array
    {
        $type = $this->config->typeOf($document->templateKey);
        $url = $this->urls->path($document->url, $document->locale, $document->webspaces[0] ?? $this->site->webspace());
        ContentRecords::remember($context->state, $document, $type, $url);

        return \array_filter([
            'content_id' => $document->id,
            'title' => $document->title,
            'type' => $type,
            'url' => $url,
            'summary' => $document->summary,
        ], static fn (string $v): bool => '' !== $v);
    }
}
