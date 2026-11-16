<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** What a path needs to reach the visitor. */
enum ContactNeed: string
{
    case None = 'none';
    case Any = 'any';
    case Email = 'email';
    case Phone = 'phone';
}
