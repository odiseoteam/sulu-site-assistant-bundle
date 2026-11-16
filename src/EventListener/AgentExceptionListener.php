<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The agent's routes are an API the widget reads: an HTTP error there is a JSON body with a code,
 * not the site's error page, which a request outside any portal cannot render.
 */
final class AgentExceptionListener
{
    private const ROUTE_PREFIXES = ['odiseo_ai_conversational_agent_', 'odiseo_sulu_site_assistant_agent_'];

    private const CODES = [400 => 'bad_request', 401 => 'unknown_session', 403 => 'forbidden', 404 => 'not_found', 405 => 'method_not_allowed', 429 => 'too_many_requests'];

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $route = $event->getRequest()->attributes->get('_route');
        if (!$exception instanceof HttpExceptionInterface || !\is_string($route) || !$this->isAgentRoute($route)) {
            return;
        }

        $status = $exception->getStatusCode();
        $event->setResponse(new JsonResponse(
            ['code' => self::CODES[$status] ?? 'error', 'message' => '' !== $exception->getMessage() ? $exception->getMessage() : 'The request could not be served.'],
            $status,
            $exception->getHeaders(),
        ));
    }

    private function isAgentRoute(string $route): bool
    {
        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (\str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
