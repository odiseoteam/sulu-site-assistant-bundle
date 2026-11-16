<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcome;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffRequest;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffTopic;

/** Applies the site's settings to a handoff request. */
interface HandoffDeskInterface
{
    public function open(HandoffRequest $request, SessionContext $session): HandoffOutcome;

    /**
     * How a case on this topic would go on now, without saving anything.
     *
     * @return array<string, string> name => description; empty when no route applies
     */
    public function paths(HandoffTopic $topic, SessionContext $session): array;
}
