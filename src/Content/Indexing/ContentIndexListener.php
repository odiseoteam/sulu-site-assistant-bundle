<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content\Indexing;

use CmsIg\Seal\Reindex\ReindexConfig;
use Sulu\Article\Domain\Event\ArticleRemovedEvent;
use Sulu\Article\Domain\Event\ArticleTranslationRemovedEvent;
use Sulu\Article\Domain\Event\ArticleWorkflowTransitionAppliedEvent;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Page\Domain\Event\PageRemovedEvent;
use Sulu\Page\Domain\Event\PageTranslationRemovedEvent;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Reindexes a page or article in the assistant's index when it is published, unpublished or removed. */
final class ContentIndexListener
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function onPageChanged(PageWorkflowTransitionAppliedEvent|PageRemovedEvent|PageTranslationRemovedEvent $event): void
    {
        $locales = $event instanceof PageRemovedEvent ? ($event->getAllLocales() ?? []) : \array_filter([$event->getResourceLocale()]);
        $this->reindex(PageInterface::RESOURCE_KEY, $event->getResourceId(), $locales);
    }

    public function onArticleChanged(ArticleWorkflowTransitionAppliedEvent|ArticleRemovedEvent|ArticleTranslationRemovedEvent $event): void
    {
        $locales = $event instanceof ArticleRemovedEvent ? ($event->getAllLocales() ?? []) : \array_filter([$event->getResourceLocale()]);
        $this->reindex(ArticleInterface::RESOURCE_KEY, $event->getResourceId(), $locales);
    }

    /** @param array<string|null> $locales */
    private function reindex(string $resourceKey, string $resourceId, array $locales): void
    {
        $identifiers = [];
        foreach ($locales as $locale) {
            if (\is_string($locale) && '' !== $locale) {
                $identifiers[] = $resourceKey . '__' . $resourceId . '__' . $locale;
            }
        }

        if ([] !== $identifiers) {
            $this->messageBus->dispatch(
                ReindexConfig::create()
                    ->withIndex(ContentReindexProvider::INDEX)
                    ->withIdentifiers($identifiers),
            );
        }
    }
}
