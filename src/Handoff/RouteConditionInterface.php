<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;

/** One key of a route's `when`. Tag it `odiseo_sulu_site_assistant.handoff_condition` with a `condition` name. */
interface RouteConditionInterface
{
    public function matches(mixed $expected, HandoffSituation $situation): bool;
}
