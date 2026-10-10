<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Double;

use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentHit;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Content\Text;

/** A handful of documents matched by shared terms, for tests. */
final class InMemoryContentIndex implements ContentIndexInterface
{
    /** @var array<string, ContentDocument> */
    private array $documents = [];

    /** @var list<array{query: string, locale: string, templates: list<string>}> */
    public array $searches = [];

    public function add(ContentDocument ...$documents): self
    {
        foreach ($documents as $document) {
            $this->documents[$document->id] = $document;
        }

        return $this;
    }

    public function search(string $query, string $locale, ?string $webspace, array $templateKeys, int $limit): array
    {
        $this->searches[] = ['query' => $query, 'locale' => $locale, 'templates' => $templateKeys];
        $terms = Text::terms($query);

        $hits = [];
        foreach ($this->documents as $document) {
            if ($document->locale !== $locale || ([] !== $templateKeys && !\in_array($document->templateKey, $templateKeys, true))) {
                continue;
            }
            $words = Text::terms($document->title . ' ' . \implode(' ', \array_map(static fn ($s): string => $s->text, $document->sections)));
            if ([] === $terms || [] !== \array_intersect($terms, $words)) {
                $hits[] = new ContentHit($document, Text::bestPassage($document->sections, $query, 400));
            }
        }

        return \array_slice($hits, 0, $limit);
    }

    public function get(string $id): ?ContentDocument
    {
        return $this->documents[$id] ?? null;
    }
}
