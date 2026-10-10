<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller;

use Odiseo\AiConversationalAgentBundle\Bridge\Symfony\Session\SessionResolver;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationActivity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A contact route opened from the handoff card. Counted for the admin, and left as a note for
 * the model's next turn so it knows the visitor went for it.
 */
final class HandoffController
{
    /** Goes into the prompt, so it stays in the prompt's language. */
    private const NOTE = 'The visitor opened the %s contact route from the handoff card.';
    private const KINDS = ['page', 'email', 'booking'];

    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly ConversationActivity $activity,
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function click(Request $request): Response
    {
        $record = $this->sessions->resolve($request);
        if (!$this->limiter->create($record->sessionId)->consume()->isAccepted()) {
            return new JsonResponse(['code' => 'too_many_clicks', 'message' => 'Too many requests.'], 429);
        }

        $kind = $request->toArray()['kind'] ?? null;
        if (!\in_array($kind, self::KINDS, true)) {
            return new JsonResponse(['code' => 'unknown_route', 'message' => 'Unknown contact route.'], 400);
        }

        $this->activity->handoffClicked($record->sessionId);
        $record->pendingAppEvents[] = \sprintf(self::NOTE, $kind);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
