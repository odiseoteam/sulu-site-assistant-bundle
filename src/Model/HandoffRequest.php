<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** What the assistant asks for. A contact here is what the visitor typed in the chat. */
final readonly class HandoffRequest
{
    public function __construct(
        public HandoffTopic $topic,
        public string $summary,
        public HandoffContact $contact = new HandoffContact(),
        public ?string $path = null,
    ) {
    }
}
