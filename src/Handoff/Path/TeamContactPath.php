<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Path;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffPathInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Message\NotifyTeam;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPathResult;
use Symfony\Component\Messenger\MessageBusInterface;

/** The team reaches the visitor. The time it promises is the card's text, not the model's. */
final class TeamContactPath implements HandoffPathInterface
{
    public const NAME = 'team_contact';

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function description(): string
    {
        return 'The team contacts the visitor.';
    }

    public function contactNeed(): ContactNeed
    {
        return ContactNeed::Any;
    }

    public function open(HandoffInterface $handoff): HandoffPathResult
    {
        $this->bus->dispatch(new NotifyTeam((int) $handoff->getId()));

        return new HandoffPathResult('The team will contact the visitor. The card says when; do not state a time yourself.');
    }
}
