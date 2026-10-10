<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Exception\DocumentNotFoundException;
use CmsIg\Seal\Search\Condition\Condition;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentReindexProvider;

/** Reads the `site_assistant` index the bundle builds. */
final class SealContentIndex implements ContentIndexInterface
{
    private const PASSAGE_CHARS = 400;

    public function __construct(private readonly EngineInterface $engine)
    {
    }

    public function search(string $query, string $locale, ?string $webspace, array $templateKeys, int $limit): array
    {
        $search = $this->engine->createSearchBuilder(ContentReindexProvider::INDEX)
            ->addFilter(Condition::equal('locale', $locale))
            ->limit($limit);

        if ('' !== \trim($query)) {
            $search->addFilter(Condition::search($query));
        }
        if (null !== $webspace) {
            $search->addFilter(Condition::equal('webspaces', $webspace));
        }
        if ([] !== $templateKeys) {
            $search->addFilter(Condition::in('templateKey', $templateKeys));
        }

        $hits = [];
        foreach ($search->getResult() as $document) {
            /** @var array<string, mixed> $document */
            $content = ContentDocument::fromDocument($document);
            $hits[] = new ContentHit($content, Text::bestPassage($content->sections, $query, self::PASSAGE_CHARS));
        }

        return $hits;
    }

    public function get(string $id): ?ContentDocument
    {
        try {
            return ContentDocument::fromDocument($this->engine->getDocument(ContentReindexProvider::INDEX, $id));
        } catch (DocumentNotFoundException) {
            return null;
        }
    }
}
