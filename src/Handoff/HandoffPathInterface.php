<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPathResult;

/** How the visitor goes on. Tag it `odiseo_sulu_site_assistant.handoff_path` with a `path` name. */
interface HandoffPathInterface
{
    /** One line for the assistant, when the visitor has to choose. */
    public function description(): string;

    public function contactNeed(): ContactNeed;

    /** Throwing marks the handoff as failed. */
    public function open(HandoffInterface $handoff): HandoffPathResult;
}
