<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\Conversation as CoreConversation;

/**
 * Locale and webspace are set on creation; the activity columns are kept by ConversationActivity
 * with bulk updates, so a running turn keeps its version. Mapping in config/doctrine.
 */
class Conversation extends CoreConversation implements ConversationInterface
{
    protected ?string $locale = null;

    protected ?string $webspaceKey = null;

    protected ?string $firstMessage = null;

    protected ?string $lastPage = null;

    protected int $turnCount = 0;

    protected ?\DateTimeImmutable $contactOpenedAt = null;

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getWebspaceKey(): ?string
    {
        return $this->webspaceKey;
    }

    public function setWebspaceKey(?string $webspaceKey): void
    {
        $this->webspaceKey = $webspaceKey;
    }

    public function getFirstMessage(): ?string
    {
        return $this->firstMessage;
    }

    public function getLastPage(): ?string
    {
        return $this->lastPage;
    }

    public function getTurnCount(): int
    {
        return $this->turnCount;
    }

    public function getContactOpenedAt(): ?\DateTimeImmutable
    {
        return $this->contactOpenedAt;
    }
}
