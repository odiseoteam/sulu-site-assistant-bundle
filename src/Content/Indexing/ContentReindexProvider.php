<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content\Indexing;

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentSection;
use Odiseo\SuluSiteAssistantBundle\Content\Text;
use Sulu\Article\Domain\Model\ArticleDimensionContentInterface;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\SecurityBundle\Entity\AccessControl;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;

/**
 * Feeds the `site_assistant` index with the live, current version of every page and article:
 * link and shadow pages, pages behind access control and excluded templates are left out.
 */
final class ContentReindexProvider implements ReindexProviderInterface
{
    public const INDEX = 'site_assistant';

    private const BATCH_SIZE = 50;
    private const SUMMARY_CHARS = 300;

    /**
     * @param list<string> $resourceKeys `pages`, `articles`
     * @param list<string> $excludedTemplates
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TemplateTextExtractor $extractor,
        private readonly array $resourceKeys,
        private readonly array $excludedTemplates = [],
    ) {
    }

    public static function getIndex(): string
    {
        return self::INDEX;
    }

    public function total(): ?int
    {
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        $identifiers = \array_values($reindexConfig->getIdentifiers());

        foreach ($this->sources() as $resourceKey => [$class, $formKey]) {
            for ($offset = 0; [] !== ($rows = $this->batch($resourceKey, $class, $identifiers, $offset)); $offset += self::BATCH_SIZE) {
                foreach ($rows as $row) {
                    $document = $this->document($resourceKey, $formKey, $row);
                    if (null !== $document) {
                        yield $document->toDocument();
                    }
                }
            }
        }
    }

    /** @return array<string, array{class-string, string}> */
    private function sources(): array
    {
        $sources = [];
        if (\in_array('pages', $this->resourceKeys, true) && \interface_exists(PageDimensionContentInterface::class)) {
            $sources[PageInterface::RESOURCE_KEY] = [PageDimensionContentInterface::class, 'page'];
        }
        if (\in_array('articles', $this->resourceKeys, true) && \interface_exists(ArticleDimensionContentInterface::class)) {
            $sources[ArticleInterface::RESOURCE_KEY] = [ArticleDimensionContentInterface::class, 'article'];
        }

        return $sources;
    }

    /**
     * @param class-string $class
     * @param list<string> $identifiers
     *
     * @return list<array<string, mixed>>
     */
    private function batch(string $resourceKey, string $class, array $identifiers, int $offset): array
    {
        $isPage = PageInterface::RESOURCE_KEY === $resourceKey;
        $owner = $isPage ? 'page' : 'article';

        $query = $this->em->getRepository($class)->createQueryBuilder('dc')
            ->leftJoin('dc.route', 'route')
            ->leftJoin('dc.' . $owner, 'owner')
            ->select('owner.uuid AS resourceId')
            ->addSelect('dc.title', 'dc.locale', 'dc.templateKey', 'dc.templateData', 'dc.excerptData', 'dc.authored', 'dc.changed')
            ->addSelect('route.slug')
            ->where('dc.stage = :stage')
            ->andWhere('dc.locale IS NOT NULL')
            ->andWhere('dc.version = :version')
            ->andWhere('route.slug IS NOT NULL')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->orderBy('dc.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults(self::BATCH_SIZE);

        // Shadow and link pages point at other content; not every content type has them.
        $metadata = $this->em->getClassMetadata($class);
        foreach (['shadowLocale', 'linkProvider'] as $field) {
            if ($metadata->hasField($field)) {
                $query->andWhere(\sprintf('dc.%s IS NULL', $field));
            }
        }

        if ($isPage) {
            $query->addSelect('owner.webspaceKey AS webspace')
                ->leftJoin(AccessControl::class, 'acl', 'WITH', 'acl.entityId = owner.uuid AND acl.entityClass = :pageClass')
                ->andWhere('acl.id IS NULL')
                ->setParameter('pageClass', Page::class);
        } else {
            $query->addSelect('dc.mainWebspace AS webspace');
        }

        if ([] !== $this->excludedTemplates) {
            $query->andWhere('dc.templateKey NOT IN (:excluded)')->setParameter('excluded', $this->excludedTemplates);
        }

        if ([] !== $identifiers && !$this->filterByIdentifiers($query, $resourceKey, $identifiers)) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $query->getQuery()->getArrayResult();
    }

    /** @param list<string> $identifiers */
    private function filterByIdentifiers(QueryBuilder $query, string $resourceKey, array $identifiers): bool
    {
        $conditions = [];
        foreach ($identifiers as $i => $identifier) {
            $parts = \explode('__', $identifier);
            if (3 !== \count($parts) || $parts[0] !== $resourceKey) {
                continue;
            }
            $conditions[] = \sprintf('(owner.uuid = :id%1$d AND dc.locale = :locale%1$d)', $i);
            $query->setParameter('id' . $i, $parts[1])->setParameter('locale' . $i, $parts[2]);
        }

        if ([] === $conditions) {
            return false;
        }
        $query->andWhere(\implode(' OR ', $conditions));

        return true;
    }

    /** @param array<string, mixed> $row */
    private function document(string $resourceKey, string $formKey, array $row): ?ContentDocument
    {
        $resourceId = \is_scalar($row['resourceId'] ?? null) ? (string) $row['resourceId'] : '';
        $locale = \is_string($row['locale'] ?? null) ? $row['locale'] : '';
        $templateKey = \is_string($row['templateKey'] ?? null) ? $row['templateKey'] : '';
        if ('' === $resourceId || '' === $locale || '' === $templateKey) {
            return null;
        }

        $templateData = \is_array($row['templateData'] ?? null) ? $row['templateData'] : [];
        $excerpt = \is_array($row['excerptData'] ?? null) ? $row['excerptData'] : [];
        /** @var array<string, mixed> $templateData */
        $sections = $this->extractor->sections($formKey, $templateKey, $locale, $templateData);

        $title = \is_string($row['title'] ?? null) && '' !== \trim($row['title']) ? \trim($row['title']) : null;
        $title ??= \is_string($excerpt['title'] ?? null) ? \trim($excerpt['title']) : '';

        $description = \is_string($excerpt['description'] ?? null) ? Text::fromHtml($excerpt['description']) : '';
        $summary = '' !== $description
            ? $description
            : ([] !== $sections ? Text::truncate(\str_replace("\n", ' ', $sections[0]->text), self::SUMMARY_CHARS) : '');

        $webspace = \is_string($row['webspace'] ?? null) ? $row['webspace'] : null;
        $authored = $row['authored'] ?? $row['changed'] ?? null;

        return new ContentDocument(
            ContentDocument::identifier($resourceKey, $resourceId, $locale),
            $resourceKey,
            $resourceId,
            $locale,
            null !== $webspace ? [$webspace] : [],
            $templateKey,
            $title,
            $summary,
            \is_string($row['slug'] ?? null) ? $row['slug'] : '',
            \array_values(\array_filter($sections, static fn (ContentSection $s): bool => '' !== $s->text)),
            self::mediaId($excerpt['image'] ?? null) ?? $this->extractor->firstMediaId($formKey, $templateKey, $locale, $templateData),
            $authored instanceof \DateTimeInterface ? $authored->format('c') : null,
        );
    }

    private static function mediaId(mixed $value): ?int
    {
        $id = \is_array($value) ? ($value['id'] ?? null) : null;

        return \is_numeric($id) ? (int) $id : null;
    }
}
