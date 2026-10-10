<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

/** One part of a page: a block of the template, or the text outside any block. */
final readonly class ContentSection
{
    public function __construct(
        public ?string $heading,
        public string $text,
    ) {
    }

    /** @return array{heading: ?string, text: string} */
    public function toArray(): array
    {
        return ['heading' => $this->heading, 'text' => $this->text];
    }

    /** @param array<mixed> $row */
    public static function fromArray(array $row): self
    {
        $heading = $row['heading'] ?? null;

        return new self(\is_string($heading) && '' !== $heading ? $heading : null, \is_string($row['text'] ?? null) ? $row['text'] : '');
    }
}
