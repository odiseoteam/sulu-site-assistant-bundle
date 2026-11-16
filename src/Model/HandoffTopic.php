<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** A family of situations. The description is what the assistant reads to pick it. */
final readonly class HandoffTopic
{
    public function __construct(
        public string $id,
        public string $description,
        public HandoffCategory $category,
        public HandoffMode $mode,
    ) {
    }
}
