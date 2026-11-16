<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\ConversationInterface as CoreConversationInterface;

/** An agent session as the site sees it. */
interface ConversationInterface extends CoreConversationInterface
{
    public function getLocale(): ?string;

    public function setLocale(?string $locale): void;

    public function getWebspaceKey(): ?string;

    public function setWebspaceKey(?string $webspaceKey): void;

    /** The visitor's first message, cut to a line, for the lists. */
    public function getFirstMessage(): ?string;

    /** The page the visitor was on at their latest message. */
    public function getLastPage(): ?string;

    public function getTurnCount(): int;

    /** When the visitor first opened a contact link from a handoff card. */
    public function getContactOpenedAt(): ?\DateTimeImmutable;
}
