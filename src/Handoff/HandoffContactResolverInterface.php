<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;

/** What the site already knows of how to reach the visitor of this session. */
interface HandoffContactResolverInterface
{
    public function known(SessionContext $session): HandoffContact;
}
