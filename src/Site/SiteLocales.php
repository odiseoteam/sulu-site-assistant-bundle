<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Site;

use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/** The webspace the assistant speaks for and its locales, default first. */
final class SiteLocales
{
    /** @var list<string>|null */
    private ?array $locales = null;

    public function __construct(
        private readonly WebspaceManagerInterface $webspaces,
        private readonly ?string $webspace,
    ) {
    }

    public function webspace(): ?string
    {
        if (null !== $this->webspace) {
            return $this->webspace;
        }
        $webspaces = $this->webspaces->getWebspaceCollection()->getWebspaces();
        $first = [] !== $webspaces ? \reset($webspaces) : null;

        return $first?->getKey();
    }

    /** @return list<string> */
    public function locales(): array
    {
        if (null === $this->locales) {
            $webspace = $this->webspaces->findWebspaceByKey($this->webspace());
            $default = $webspace?->getDefaultLocalization()?->getLocale();
            $all = \array_map(static fn ($l): string => $l->getLocale(), $webspace?->getAllLocalizations() ?? []);
            $this->locales = \array_values(\array_unique(\array_filter([$default, ...$all], 'is_string')));
        }

        return $this->locales;
    }

    public function defaultLocale(): string
    {
        return $this->locales()[0] ?? 'en';
    }
}
