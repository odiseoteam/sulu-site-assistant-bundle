<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\Message as CoreMessage;

/** A transcript message. */
class Message extends CoreMessage implements MessageInterface
{
}
