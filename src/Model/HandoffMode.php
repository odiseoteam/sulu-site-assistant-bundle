<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** Whether a topic hands off at once or offers first. */
enum HandoffMode: string
{
    case Direct = 'direct';
    case Offer = 'offer';
}
