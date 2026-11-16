<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

/**
 * One kind of editable item in the admin (rules, knowledge documents): what
 * there is, what the configuration says, how it is edited and what blocks a publication.
 */
interface EditableSectionInterface
{
    /** The route and translation segment: rule, document. */
    public function name(): string;

    /** The version store's kind. */
    public function kind(): string;

    public function canCreate(): bool;

    /**
     * The groups the index splits the items into, in order: key → label.
     *
     * @return array<string, string>
     */
    public function groups(): array;

    /** @return list<EditableItem> */
    public function items(): array;

    public function item(string $key): ?EditableItem;

    /**
     * The configured item, as data; null when it only exists in the admin.
     *
     * @return array<string, mixed>|null
     */
    public function defaultData(string $key): ?array;

    /**
     * The fields the comparison shows, in order: field → label.
     *
     * @return array<string, string>
     */
    public function fields(): array;

    /**
     * The submitted form data reduced to what a version keeps.
     *
     * @param array<string, mixed> $submitted
     *
     * @return array<string, mixed>
     */
    public function normalize(array $submitted): array;

    /**
     * The fields the admin's editor shows, in order: name, type (text, textarea, markdown, select,
     * toggle), label, and for a select its options (value => label) or for a text its maximum.
     *
     * @return list<array{name: string, type: string, label: string, options?: array<string, string>, max?: int, required?: bool}>
     */
    public function editorFields(): array;

    /**
     * What stops $data from being published, as translation keys with their parameters.
     *
     * @param array<string, mixed> $data
     *
     * @return list<array{0: string, 1: array<string, string|int>}>
     */
    public function problems(string $key, array $data, bool $isNew): array;
}
