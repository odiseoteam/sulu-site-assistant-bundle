<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

use Odiseo\AiConversationalAgentBundle\Content\Version;

/**
 * An editable item as the admin lists it: the data in use, where it comes from, its version in
 * use (1 for the configured one) and its draft, if any.
 */
final readonly class EditableItem
{
    /** @param array<string, mixed> $data the version in use, or the draft for an item never published */
    public function __construct(
        public string $key,
        public string $title,
        public string $group,
        public array $data,
        public Origin $origin,
        public int $number,
        public bool $hasDefault,
        public bool $enabled = true,
        public ?Version $published = null,
        public ?Version $draft = null,
    ) {
    }

    /**
     * What the edit form starts from: the draft, else the version in use.
     *
     * @return array<string, mixed>
     */
    public function editableData(): array
    {
        return $this->draft->data ?? $this->data;
    }

    public function isPublished(): bool
    {
        return $this->number > 0;
    }

    public function isDefault(): bool
    {
        return Origin::File === $this->origin;
    }

    /** When it last changed, and who changed it: the draft's, else the version's. */
    public function lastChange(): ?Version
    {
        return $this->draft ?? $this->published;
    }
}
