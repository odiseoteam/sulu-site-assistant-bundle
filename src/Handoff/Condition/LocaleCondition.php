<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Condition;

use Odiseo\SuluSiteAssistantBundle\Handoff\RouteConditionInterface;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;

/** `locale: es` or `locale: [es, pt]`: the language of the visitor's page. */
final class LocaleCondition implements RouteConditionInterface
{
    public function matches(mixed $expected, HandoffSituation $situation): bool
    {
        $locales = \is_array($expected) ? $expected : [$expected];

        return null !== $situation->locale && \in_array($situation->locale, $locales, true);
    }
}
