<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Conversation;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\ConversationInitializerInterface;
use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\ConversationInterface as CoreConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;

/** Stamps a new conversation with the page's locale and the webspace it started on. */
final class ConversationInitializer implements ConversationInitializerInterface
{
    public function __construct(private readonly VisitorContext $visitor)
    {
    }

    public function initialize(CoreConversationInterface $conversation): void
    {
        if (!$conversation instanceof ConversationInterface) {
            return;
        }

        $conversation->setLocale($this->visitor->locale());
        $conversation->setWebspaceKey($this->visitor->webspace());
    }
}
