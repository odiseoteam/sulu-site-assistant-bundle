<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\SpendEntry as CoreSpendEntry;

/** A billed model call, keyed by session id so budgets still add up after it is pruned. */
class SpendEntry extends CoreSpendEntry implements SpendEntryInterface
{
}
