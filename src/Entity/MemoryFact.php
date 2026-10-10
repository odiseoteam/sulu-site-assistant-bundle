<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\MemoryFact as CoreMemoryFact;

/** A remembered fact per visitor. The subject outlives the conversation. */
class MemoryFact extends CoreMemoryFact implements MemoryFactInterface
{
}
