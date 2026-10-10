<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Site;

use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/** A route slug as a path on the site, with the portal's locale prefix. */
final class PageUrlResolver
{
    public function __construct(
        private readonly WebspaceManagerInterface $webspaces,
        private readonly string $environment,
    ) {
    }

    public function path(string $slug, string $locale, ?string $webspace): string
    {
        $url = null;
        try {
            $url = $this->webspaces->findUrlByResourceLocator($slug, $this->environment, $locale, $webspace);
        } catch (\Throwable) {
        }

        $path = \is_string($url) ? \parse_url($url, \PHP_URL_PATH) : null;

        return \is_string($path) && '' !== $path ? $path : '/' . $locale . ('/' === $slug ? '' : $slug);
    }
}
