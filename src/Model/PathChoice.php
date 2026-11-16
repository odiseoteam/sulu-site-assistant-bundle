<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** Who picks when a route leaves more than one path. */
enum PathChoice: string
{
    case Visitor = 'visitor';
    case Priority = 'priority';
}
