<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** What a path did: the sentence the assistant can tell, and the buttons for the visitor. */
final readonly class HandoffPathResult
{
    /** @param list<array{label: string, url: string}> $actions */
    public function __construct(
        public string $nextStep,
        public array $actions = [],
    ) {
    }
}
