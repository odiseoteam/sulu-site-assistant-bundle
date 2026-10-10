<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Conversation;

use Odiseo\AiConversationalAgentBundle\Host\TurnHook;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** Counts the turn and keeps the first message and the current page before the turn streams. */
final class ActivityTurnHook implements TurnHook
{
    public function __construct(
        private readonly ConversationActivity $activity,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function beforeTurn(SessionContext $session): void
    {
        $message = '';
        $request = $this->requestStack->getMainRequest();
        if (null !== $request && 'json' === $request->getContentTypeFormat()) {
            $body = $request->toArray();
            $message = \is_string($body['message'] ?? null) ? $body['message'] : '';
        }

        $page = $session->surface['url'] ?? null;
        $this->activity->turnStarted($session->sessionId, $message, \is_string($page) ? $page : null);
    }
}
