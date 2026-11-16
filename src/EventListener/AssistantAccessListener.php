<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\EventListener;

use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * While access is restricted, the token in the query string opens the assistant for the browser
 * and the page is reloaded without it, so the token stays out of history and shared links. The
 * redirect is private and not storable, so no cache keeps it.
 */
final class AssistantAccessListener
{
    public function __construct(private readonly AssistantAccess $access)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $parameter = $this->access->queryParameter();
        if (!$event->isMainRequest() || !$this->access->restricted() || !$request->query->has($parameter) || !$request->isMethodSafe()) {
            return;
        }

        $response = new RedirectResponse($this->withoutParameter($request, $parameter));
        if ($this->access->matches($request->query->all()[$parameter])) {
            foreach ($this->access->grant($request) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $event->setResponse($response);
    }

    // Relative, so a proxy that hides the scheme or the public host does not change where it goes;
    // one leading slash, or "//host" would send the browser to another site.
    private function withoutParameter(Request $request, string $parameter): string
    {
        $query = $request->query->all();
        unset($query[$parameter]);
        $path = '/' . \ltrim($request->getBaseUrl() . $request->getPathInfo(), '/\\');

        return $path . ([] === $query ? '' : '?' . \http_build_query($query));
    }
}
