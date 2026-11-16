<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\ContentVersion as CoreContentVersion;

/** A draft or a published version of a rule or a knowledge document edited in the admin. */
class ContentVersion extends CoreContentVersion implements ContentVersionInterface
{
}
