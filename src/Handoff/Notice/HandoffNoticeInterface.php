<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Notice;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;

/** How the team hears of a case. Tag it `odiseo_sulu_site_assistant.handoff_notice`. */
interface HandoffNoticeInterface
{
    /** Throwing leaves the case without notice, and the message is retried. */
    public function send(HandoffInterface $handoff): void;
}
