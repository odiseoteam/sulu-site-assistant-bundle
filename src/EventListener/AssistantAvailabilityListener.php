<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\EventListener;

use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use Odiseo\SuluSiteAssistantBundle\Settings\WebspaceSettings;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The agent's routes answer 404 on a webspace whose settings turn the assistant off, and to a
 * browser restricted access keeps out.
 */
final class AssistantAvailabilityListener
{
    private const ROUTE_PREFIXES = ['odiseo_ai_conversational_agent_', 'odiseo_sulu_site_assistant_agent_'];

    public function __construct(
        private readonly WebspaceSettings $settings,
        private readonly AssistantAccess $access,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if (!$event->isMainRequest() || !\is_string($route) || !$this->isAgentRoute($route)) {
            return;
        }

        if (!$this->settings->enabled()) {
            throw new NotFoundHttpException('The site assistant is off on this webspace.');
        }
        if (!$this->access->isOpen($event->getRequest())) {
            throw new NotFoundHttpException('The site assistant is restricted.');
        }
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
