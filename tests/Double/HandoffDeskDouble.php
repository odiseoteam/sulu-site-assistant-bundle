<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Double;

use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffDeskInterface;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcome;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffRequest;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffTopic;

/** Answers with the outcome a test sets and keeps what it was asked. */
final class HandoffDeskDouble implements HandoffDeskInterface
{
    /** @var list<HandoffRequest> */
    public array $requests = [];

    /** @param array<string, string> $paths */
    public function __construct(
        public HandoffOutcome $outcome,
        public array $paths = ['visitor_writes' => 'The visitor writes to us now.'],
    ) {
    }

    public function open(HandoffRequest $request, SessionContext $session): HandoffOutcome
    {
        $this->requests[] = $request;

        return $this->outcome;
    }

    public function paths(HandoffTopic $topic, SessionContext $session): array
    {
        return $this->paths;
    }
}
