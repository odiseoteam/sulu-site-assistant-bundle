<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;
use Odiseo\SuluSiteAssistantBundle\Repository\ConversationRepository;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;

/**
 * A website has no account to read a contact from: what the visitor left on an earlier case of
 * the same conversation is what the site knows. A site with its own sign-in replaces this.
 */
final class ConversationContactResolver implements HandoffContactResolverInterface
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly HandoffRepository $handoffs,
    ) {
    }

    public function known(SessionContext $session): HandoffContact
    {
        $conversation = $this->conversations->findOneBySessionId($session->sessionId);
        if (!$conversation instanceof ConversationInterface) {
            return new HandoffContact();
        }

        $contact = new HandoffContact();
        foreach ($this->handoffs->findByConversation($conversation) as $handoff) {
            $contact = $contact->orElse(new HandoffContact($handoff->getContactEmail(), $handoff->getContactPhone(), $handoff->getContactName()));
        }

        return $contact;
    }
}
