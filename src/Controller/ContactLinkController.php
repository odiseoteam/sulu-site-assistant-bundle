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
 * A contact link opened from a handoff card. Counted for the admin, and left as a note for the
 * model's next turn so it knows the visitor went for it.
 */
final class ContactLinkController
{
    /** Goes into the prompt, so it stays in the prompt's language. */
    private const NOTE = 'The visitor opened the %s contact link from the handoff card.';

    /** @param list<string> $links the configured contact link names */
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly ConversationActivity $activity,
        private readonly RateLimiterFactoryInterface $limiter,
        private readonly array $links,
    ) {
    }

    public function opened(Request $request): Response
    {
        $record = $this->sessions->resolve($request);
        if (!$this->limiter->create($record->sessionId)->consume()->isAccepted()) {
            return new JsonResponse(['code' => 'too_many_clicks', 'message' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $link = $request->toArray()['link'] ?? null;
        } catch (\Throwable) {
            $link = null;
        }
        if (!\is_string($link) || !\in_array($link, $this->links, true)) {
            return new JsonResponse(['code' => 'unknown_link', 'message' => 'Unknown contact link.'], Response::HTTP_BAD_REQUEST);
        }

        $this->activity->contactOpened($record->sessionId);
        $record->pendingAppEvents[] = \sprintf(self::NOTE, $link);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
