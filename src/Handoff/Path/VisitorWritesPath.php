<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Path;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffPathInterface;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPathResult;

/** The visitor writes to the team. The card turns `contact.links` into buttons that carry the reference. */
final class VisitorWritesPath implements HandoffPathInterface
{
    public const NAME = 'visitor_writes';

    public function description(): string
    {
        return 'The visitor writes to us now, with the reference already in the message.';
    }

    public function contactNeed(): ContactNeed
    {
        return ContactNeed::None;
    }

    public function open(HandoffInterface $handoff): HandoffPathResult
    {
        return new HandoffPathResult('Nobody sees the case until the visitor writes: the card has the button, with the reference in the message.');
    }
}
