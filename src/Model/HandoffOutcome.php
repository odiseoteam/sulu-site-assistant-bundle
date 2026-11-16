<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** What came of a request. It carries no contact but the masked phone. */
final readonly class HandoffOutcome
{
    /**
     * @param array<string, string> $paths name => description: the ones to choose from, or that work without the missing contact
     * @param array<string, bool> $alternatives paths that would contact the visitor instead => whether the contact on hand covers them
     * @param list<string> $selfServe other paths the visitor can take without any contact
     */
    public function __construct(
        public HandoffOutcomeStatus $status,
        public ?string $reference = null,
        public ?string $path = null,
        public array $paths = [],
        public ?HandoffPathResult $result = null,
        public ?ContactNeed $need = null,
        public ?string $maskedPhone = null,
        public bool $open = true,
        public array $alternatives = [],
        public array $selfServe = [],
    ) {
    }
}
