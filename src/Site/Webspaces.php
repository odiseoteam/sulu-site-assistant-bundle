<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Site;

use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/** The site's webspaces and their locales, default first. */
final class Webspaces
{
    /** @var array<string, list<string>> */
    private array $locales = [];

    public function __construct(
        private readonly WebspaceManagerInterface $webspaces,
        private readonly ?string $default = null,
    ) {
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->webspaces->getWebspaceCollection()->getWebspaces() as $webspace) {
            $keys[] = $webspace->getKey();
        }

        return $keys;
    }

    public function has(?string $key): bool
    {
        return null !== $key && null !== $this->webspaces->findWebspaceByKey($key);
    }

    /** The configured webspace, or the first one. */
    public function default(): ?string
    {
        if (null !== $this->default) {
            return $this->default;
        }

        return $this->keys()[0] ?? null;
    }

    public function name(string $key): string
    {
        return $this->webspaces->findWebspaceByKey($key)?->getName() ?? $key;
    }

    /** @return list<string> the webspace's locales, the default first */
    public function locales(?string $webspace = null): array
    {
        $key = $webspace ?? $this->default();
        if (null === $key) {
            return [];
        }

        if (!isset($this->locales[$key])) {
            $found = $this->webspaces->findWebspaceByKey($key);
            $default = $found?->getDefaultLocalization()?->getLocale();
            $all = \array_map(static fn (Localization $localization): string => $localization->getLocale(), $found?->getAllLocalizations() ?? []);
            $this->locales[$key] = \array_values(\array_unique(\array_filter([$default, ...$all], 'is_string')));
        }

        return $this->locales[$key];
    }

    public function defaultLocale(?string $webspace = null): string
    {
        return $this->locales($webspace)[0] ?? 'en';
    }
}
