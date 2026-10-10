<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle;

/** The site's settings the capabilities read, filled from `odiseo_sulu_site_assistant`. */
final readonly class SiteAssistantConfig
{
    /**
     * @param array<string, list<string>> $contentTypes type name → template keys
     * @param list<string> $groundingTerms
     * @param list<string> $groundingCues
     */
    public function __construct(
        public array $contentTypes = [],
        public string $searchNotes = '',
        public array $groundingTerms = [],
        public array $groundingCues = [],
        public int $maxSearchResults = 6,
        public ?string $contactPage = null,
        public ?string $contactEmail = null,
        public ?string $bookingUrl = null,
        public string $imageFormat = 'sulu-400x400',
    ) {
    }

    /** The type name a template belongs to, or the template key when no type lists it. */
    public function typeOf(string $templateKey): string
    {
        foreach ($this->contentTypes as $type => $templates) {
            if (\in_array($templateKey, $templates, true)) {
                return $type;
            }
        }

        return $templateKey;
    }

    /** @return list<string> */
    public function templatesOf(?string $type): array
    {
        return null !== $type ? ($this->contentTypes[$type] ?? []) : [];
    }
}
