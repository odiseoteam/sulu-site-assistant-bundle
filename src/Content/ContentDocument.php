<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

/**
 * A published page or article as the assistant reads it. The id is the index's
 * (`{resourceKey}__{resourceId}__{locale}`); the url is the route slug, resolved per request.
 */
final readonly class ContentDocument
{
    /**
     * @param list<string> $webspaces
     * @param list<ContentSection> $sections
     */
    public function __construct(
        public string $id,
        public string $resourceKey,
        public string $resourceId,
        public string $locale,
        public array $webspaces,
        public string $templateKey,
        public string $title,
        public string $summary,
        public string $url,
        public array $sections,
        public ?int $mediaId = null,
        public ?string $authoredAt = null,
    ) {
    }

    public static function identifier(string $resourceKey, string $resourceId, string $locale): string
    {
        return $resourceKey . '__' . $resourceId . '__' . $locale;
    }

    /** @return array<string, mixed> */
    public function toDocument(): array
    {
        return [
            'id' => $this->id,
            'resourceKey' => $this->resourceKey,
            'resourceId' => $this->resourceId,
            'locale' => $this->locale,
            'webspaces' => $this->webspaces,
            'templateKey' => $this->templateKey,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => \array_map(static fn (ContentSection $s): string => $s->text, $this->sections),
            'url' => $this->url,
            'mediaId' => $this->mediaId,
            'authoredAt' => $this->authoredAt,
            'sections' => ['items' => \array_map(static fn (ContentSection $s): array => $s->toArray(), $this->sections)],
        ];
    }

    /** @param array<string, mixed> $document */
    public static function fromDocument(array $document): self
    {
        $sections = $document['sections']['items'] ?? [];
        $webspaces = $document['webspaces'] ?? [];
        $mediaId = $document['mediaId'] ?? null;
        $authoredAt = $document['authoredAt'] ?? null;

        return new self(
            self::string($document['id'] ?? null),
            self::string($document['resourceKey'] ?? null),
            self::string($document['resourceId'] ?? null),
            self::string($document['locale'] ?? null),
            \is_array($webspaces) ? \array_values(\array_filter($webspaces, 'is_string')) : [],
            self::string($document['templateKey'] ?? null),
            self::string($document['title'] ?? null),
            self::string($document['summary'] ?? null),
            self::string($document['url'] ?? null),
            \is_array($sections) ? \array_values(\array_map(ContentSection::fromArray(...), \array_filter($sections, 'is_array'))) : [],
            \is_numeric($mediaId) && (int) $mediaId > 0 ? (int) $mediaId : null,
            \is_string($authoredAt) ? $authoredAt : null,
        );
    }

    private static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
