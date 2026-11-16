<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

use Odiseo\AiConversationalAgentBundle\Content\Version;
use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;

/**
 * The listing every section shares: the configured items in their order, with what was
 * published over them, then the items that only exist in the admin, in key order.
 */
abstract class AbstractEditableSection implements EditableSectionInterface
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $defaults = null;

    public function __construct(protected readonly VersionStoreInterface $versions)
    {
    }

    /**
     * The configured items, by key, as data.
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function configuredItems(): array;

    /** @param array<string, mixed> $data */
    abstract protected function titleOf(string $key, array $data): string;

    /** @param array<string, mixed> $data */
    abstract protected function groupOf(string $key, array $data): string;

    /** @param array<string, mixed> $data */
    protected function enabledOf(array $data): bool
    {
        return false !== ($data['enabled'] ?? true);
    }

    public function canCreate(): bool
    {
        return true;
    }

    public function defaultData(string $key): ?array
    {
        return $this->defaults()[$key] ?? null;
    }

    public function items(): array
    {
        $published = $this->versions->published($this->kind());
        $drafts = $this->versions->drafts($this->kind());

        $items = [];
        foreach ($this->defaults() as $key => $data) {
            $items[] = $this->build((string) $key, $data, $published[$key] ?? null, $drafts[$key] ?? null);
        }
        $own = \array_diff_key($published + $drafts, $this->defaults());
        \ksort($own, \SORT_STRING);
        foreach (\array_keys($own) as $key) {
            $items[] = $this->build((string) $key, null, $published[$key] ?? null, $drafts[$key] ?? null);
        }

        return $items;
    }

    public function item(string $key): ?EditableItem
    {
        $default = $this->defaultData($key);
        $published = $this->versions->published($this->kind())[$key] ?? null;
        $draft = $this->versions->draft($this->kind(), $key);
        if (null === $default && null === $published && null === $draft) {
            return null;
        }

        return $this->build($key, $default, $published, $draft);
    }

    /** @param array<string, mixed>|null $default */
    private function build(string $key, ?array $default, ?Version $published, ?Version $draft): EditableItem
    {
        $hasDefault = null !== $default;
        $edited = null !== $published && !$published->isDefault();
        $data = $edited ? $published->data : ($default ?? $draft->data ?? []);
        $data ??= [];

        return new EditableItem(
            $key,
            $this->titleOf($key, $data),
            $this->groupOf($key, $data),
            $data,
            match (true) {
                !$hasDefault => Origin::Admin,
                $edited => Origin::FileEdited,
                default => Origin::File,
            },
            $published->number ?? ($hasDefault ? 1 : 0),
            $hasDefault,
            $this->enabledOf($data),
            $published,
            $draft,
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function defaults(): array
    {
        return $this->defaults ??= $this->configuredItems();
    }
}
