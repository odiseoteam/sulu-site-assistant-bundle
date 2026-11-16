<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Eval;

use Odiseo\AiConversationalAgentBundle\Eval\EvalCase;
use Odiseo\AiConversationalAgentBundle\Eval\EvalEnvironmentInterface;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Visitor\ConsoleVisitor;

/**
 * The site's side of a case. Each case runs as a fresh visitor, and the precondition keys a case
 * may set in `state`:
 *
 * - `webspace`: the webspace it talks on; the default one without it.
 * - `locale`: the language of the visitor's page; the webspace's default without it.
 * - `seen_pages`: content ids the visitor has already seen, as a search would have left them.
 */
final class SiteEvalEnvironment implements EvalEnvironmentInterface
{
    public function __construct(
        private readonly ConsoleVisitor $console,
        private readonly ContentIndexInterface $index,
        private readonly PageUrlResolver $urls,
        private readonly SiteAssistantConfig $config,
    ) {
    }

    public function prepare(EvalCase $case, TurnState $state): void
    {
        $this->console->prepare(Scalar::nullableString($case->state['locale'] ?? null), Scalar::nullableString($case->state['webspace'] ?? null));

        foreach (Scalar::strings($case->state['seen_pages'] ?? null) as $id) {
            $document = $this->index->get($id);
            if (null === $document) {
                throw new \RuntimeException(\sprintf('Case %s: page %s is not in the index.', $case->id, $id));
            }
            ContentRecords::remember($state, $document, $this->config->typeOf($document->templateKey), $this->urls->path($document->url, $document->locale, $document->webspaces[0] ?? null));
        }
    }

    public function snapshot(): array
    {
        return [];
    }
}
