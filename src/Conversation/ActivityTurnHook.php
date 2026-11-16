<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Conversation;

use Odiseo\AiConversationalAgentBundle\Host\TurnHookInterface;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** Counts the turn and keeps the first message and the current page before the turn streams. */
final class ActivityTurnHook implements TurnHookInterface
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
            try {
                $body = $request->toArray();
            } catch (\Throwable) {
                $body = [];
            }
            // A retry answers the same message again: it is not another turn.
            if (true === ($body['retry'] ?? false)) {
                return;
            }
            $message = \is_string($body['message'] ?? null) ? $body['message'] : '';
        }

        $page = $session->surface['url'] ?? null;
        $this->activity->turnStarted($session->sessionId, $message, \is_string($page) ? $page : null);
    }
}
