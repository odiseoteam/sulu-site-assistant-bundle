<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Condition;

use Odiseo\SuluSiteAssistantBundle\Handoff\RouteConditionInterface;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;

/** `contact: any`, `email` or `phone`: that contact is known. */
final class ContactCondition implements RouteConditionInterface
{
    public function matches(mixed $expected, HandoffSituation $situation): bool
    {
        $need = \is_string($expected) ? ContactNeed::tryFrom($expected) : null;

        return null !== $need && $situation->contact->satisfies($need);
    }
}
