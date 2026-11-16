<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Event;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Symfony\Contracts\EventDispatcher\Event;

/** A handoff moved. Integrations (a CRM, a chat channel) listen here instead of touching the bundle. */
final class HandoffEvent extends Event
{
    public const OPENED = 'odiseo_sulu_site_assistant.handoff.opened';

    public const UPDATED = 'odiseo_sulu_site_assistant.handoff.updated';

    public const PATH_OPENED = 'odiseo_sulu_site_assistant.handoff.path_opened';

    public const FAILED = 'odiseo_sulu_site_assistant.handoff.failed';

    public const STATE_CHANGED = 'odiseo_sulu_site_assistant.handoff.state_changed';

    public function __construct(public readonly HandoffInterface $handoff)
    {
    }
}
