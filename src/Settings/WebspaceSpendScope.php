<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\AiConversationalAgentBundle\Budget\SpendScopeResolverInterface;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;

/** Spend is recorded under the webspace's key, so a webspace can have its own day and month caps. */
final class WebspaceSpendScope implements SpendScopeResolverInterface
{
    public function __construct(private readonly CurrentWebspace $webspace)
    {
    }

    public function scope(): ?string
    {
        return $this->webspace->key();
    }
}
