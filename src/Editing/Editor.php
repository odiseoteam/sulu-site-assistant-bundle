<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

use Odiseo\AiConversationalAgentBundle\Content\Version;
use Odiseo\AiConversationalAgentBundle\Content\VersionState;
use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;

/**
 * What the admin does with an item: save a draft, publish, restore an old version to a draft,
 * go back to the configured item, drop a draft. Publishing checks the section's caps first.
 */
final class Editor
{
    public function __construct(private readonly VersionStoreInterface $versions)
    {
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(EditableSectionInterface $section, string $key, array $data, ?string $author): Version
    {
        return $this->versions->saveDraft($section->kind(), $key, $data, $author);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return Version|list<array{0: string, 1: array<string, string|int>}> the version, or what stops it
     */
    public function publish(EditableSectionInterface $section, string $key, array $data, ?string $author, ?string $note, bool $isNew = false): Version|array
    {
        $problems = $section->problems($key, $data, $isNew);
        if ([] !== $problems) {
            return $problems;
        }

        return $this->versions->publish($section->kind(), $key, $data, $author, $note, null !== $section->defaultData($key));
    }

    /** Copies an old version (1 is the configured item) to the draft; false when there is none. */
    public function restore(EditableSectionInterface $section, string $key, int $number, ?string $author): bool
    {
        $data = $this->dataOf($section, $key, $number);
        if (null === $data) {
            return false;
        }
        $this->versions->saveDraft($section->kind(), $key, $data, $author);

        return true;
    }

    /** Publishes a version that goes back to the configured item; null when there is none. */
    public function reset(EditableSectionInterface $section, string $key, ?string $author, ?string $note = null): ?Version
    {
        if (null === $section->defaultData($key)) {
            return null;
        }

        return $this->versions->publish($section->kind(), $key, null, $author, $note, true);
    }

    public function discardDraft(EditableSectionInterface $section, string $key): void
    {
        $this->versions->discardDraft($section->kind(), $key);
    }

    /**
     * The versions of an item as the history lists them, newest first, ending with the configured
     * one as version 1 when it has one.
     *
     * @return list<Version>
     */
    public function history(EditableSectionInterface $section, string $key): array
    {
        $history = $this->versions->history($section->kind(), $key);
        if (null !== $default = $section->defaultData($key)) {
            $history[] = new Version($section->kind(), $key, 1, VersionState::Published, $default, null, null, new \DateTimeImmutable('@0'));
        }

        return $history;
    }

    /**
     * What a version holds; a version back to the default holds the configured item.
     *
     * @return array<string, mixed>|null
     */
    public function dataOf(EditableSectionInterface $section, string $key, int $number): ?array
    {
        foreach ($this->history($section, $key) as $version) {
            if ($version->number === $number) {
                return $version->data ?? $section->defaultData($key);
            }
        }

        return null;
    }

    /** The version with $number, as the history lists it. */
    public function version(EditableSectionInterface $section, string $key, int $number): ?Version
    {
        foreach ($this->history($section, $key) as $version) {
            if ($version->number === $number) {
                return $version;
            }
        }

        return null;
    }
}
