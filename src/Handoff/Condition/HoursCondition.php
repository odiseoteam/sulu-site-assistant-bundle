<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Condition;

use Odiseo\SuluSiteAssistantBundle\Handoff\RouteConditionInterface;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;

/** `hours: open` or `hours: closed`. */
final class HoursCondition implements RouteConditionInterface
{
    public function matches(mixed $expected, HandoffSituation $situation): bool
    {
        return ($situation->schedule->isOpen($situation->now) ? 'open' : 'closed') === $expected;
    }
}
