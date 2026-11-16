<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** How a route treats what its path needs: required stops the handoff until it is there. */
enum ContactPolicy: string
{
    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';
}
