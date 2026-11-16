<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Prompt;

use Odiseo\AiConversationalAgentBundle\Agent\ContextProviderInterface;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;

/**
 * What the site tells the model on each request: the language of the page and which page the
 * visitor is on, and the pages already read so they are not searched again. Kept small: it sits
 * after the cache breakpoint.
 */
final class SiteContextProvider implements ContextProviderInterface
{
    private const SEEN = 20;

    public function __construct(private readonly VisitorContext $visitor)
    {
    }

    public function contextPayload(SessionContext $session, TurnState $state): array
    {
        $payload = ['visitor' => ['page_language' => $this->visitor->locale()]];

        $page = \array_filter([
            'url' => \is_string($session->surface['url'] ?? null) ? \mb_substr($session->surface['url'], 0, 255) : null,
            'title' => \is_string($session->surface['title'] ?? null) ? \mb_substr($session->surface['title'], 0, 160) : null,
        ]);
        if ([] !== $page) {
            $payload['current_page'] = $page;
        }

        $seen = [];
        foreach ($state->seenRecords() as $record) {
            if (ContentRecords::KIND === $record->kind) {
                $seen[] = ['content_id' => $record->id, 'title' => $record->data['title'] ?? null];
            }
        }
        if ([] !== $seen) {
            $payload['pages_seen'] = \array_slice($seen, -self::SEEN);
        }

        return $payload;
    }
}
