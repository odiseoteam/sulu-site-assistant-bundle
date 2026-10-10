<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

/** A search match: the document and the passage that matched best. */
final readonly class ContentHit
{
    public function __construct(
        public ContentDocument $document,
        public ?string $passage,
    ) {
    }
}
