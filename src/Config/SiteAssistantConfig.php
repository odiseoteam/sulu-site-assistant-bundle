<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Config;

/**
 * The site's settings the capabilities read, filled from `odiseo_sulu_site_assistant`. Constant
 * per process, since it changes the cached prompt. Defaults are neutral: no lexicon means no
 * forced search.
 */
final readonly class SiteAssistantConfig
{
    /**
     * What each card of the widget already shows and which chips fit beside it, by component. A
     * site that changes a card in its widget replaces its note.
     */
    public const CHIP_NOTES = [
        'pages' => 'The card already shows each page with its link. Its chips act on the pages shown: compare two of them, ask about one or narrow them; none asks to see a page the card already shows or more of the same.',
        'comparison' => 'The card already shows the options side by side. Its chips choose one of them, ask about a difference or ask for a person to talk it over; none asks for this comparison again.',
        'guide' => 'The card already shows the steps and the pages they come from. Its chips ask about one step or what comes next; none repeats a step the card shows.',
    ];

    /**
     * @param array<string, list<string>> $contentTypes type name → template keys
     * @param list<string> $contentTerms grounding lexicon of the site's offer
     * @param list<string> $contentCues
     * @param list<string> $knowledgeTerms grounding lexicon of the organisation's terms
     * @param list<string> $knowledgeCues
     * @param array<string, string> $chipNotes by component; an empty note is left out
     */
    public function __construct(
        public array $contentTypes = [],
        public string $searchNotes = '',
        public array $contentTerms = [],
        public array $contentCues = [],
        public array $knowledgeTerms = [],
        public array $knowledgeCues = [],
        public int $maxSearchResults = 6,
        public array $chipNotes = self::CHIP_NOTES,
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

    public function chipNote(string $component): string
    {
        return $this->chipNotes[$component] ?? '';
    }
}
