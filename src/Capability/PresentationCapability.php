<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Capability\CapabilityInterface;
use Odiseo\AiConversationalAgentBundle\Capability\PromptFragment;
use Odiseo\AiConversationalAgentBundle\Capability\PromptSection;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\AiConversationalAgentBundle\Streaming\ToolOutcome;
use Odiseo\SuluSiteAssistantBundle\Capability\Presentation\CardComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\Presentation\ComparisonComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\Presentation\GuideComponent;
use Odiseo\SuluSiteAssistantBundle\Capability\Presentation\PagesComponent;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;

/**
 * Page cards: a shortlist, a comparison and a guide. The model picks content ids and writes the
 * reasons; title, link, summary and image come from what the session read. The order here is
 * the order of the tools in the prompt.
 */
final class PresentationCapability implements CapabilityInterface
{
    /** @var list<CardComponent> */
    private readonly array $cards;

    public function __construct(
        ContentRecords $records,
        private readonly SiteAssistantConfig $config = new SiteAssistantConfig(),
    ) {
        $this->cards = [new PagesComponent($records), new ComparisonComponent($records), new GuideComponent($records)];
    }

    public function name(): string
    {
        return 'presentation';
    }

    public function tools(): array
    {
        return \array_map(static fn (CardComponent $card) => $card->tool(), $this->cards);
    }

    public function promptFragments(): array
    {
        return [new PromptFragment(
            PromptSection::Presentation,
            '- A card names pages by content_id and nothing else: title, link, summary and image are joined from what the session retrieved. Your own words go in the reasons, pros and cons, steps and headings, in the visitor\'s language. Use present_pages for two or more options, present_comparison once the visitor weighs finalists and present_guide for how something works; a single page is a link in your text, not a card.',
            priority: 10,
        )];
    }

    public function groundingRules(): array
    {
        return [];
    }

    public function components(): array
    {
        return \array_map(function (CardComponent $card): PresentationComponent {
            $c = $card->component();

            return new PresentationComponent($c->tool, $c->component, $c->validate, $c->enrich, $c->partial, $c->chips, $this->config->chipNote($c->component));
        }, $this->cards);
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        return ToolOutcome::error(\sprintf('Unknown tool: %s', $tool));
    }
}
