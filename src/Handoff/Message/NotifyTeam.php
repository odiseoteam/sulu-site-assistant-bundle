<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Message;

/** Tells the team of a case, out of the chat's turn when the site routes it to a transport. */
final readonly class NotifyTeam
{
    public function __construct(public int $handoffId)
    {
    }
}
