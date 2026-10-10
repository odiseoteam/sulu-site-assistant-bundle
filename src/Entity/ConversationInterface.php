<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\Model\ConversationInterface as CoreConversationInterface;

interface ConversationInterface extends CoreConversationInterface
{
    public function getLocale(): ?string;

    public function setLocale(?string $locale): void;

    public function getWebspaceKey(): ?string;

    public function setWebspaceKey(?string $webspaceKey): void;

    public function getFirstMessage(): ?string;

    public function getLastPage(): ?string;

    public function getTurnCount(): int;

    public function getHandoffShownAt(): ?\DateTimeImmutable;

    public function getHandoffClickedAt(): ?\DateTimeImmutable;
}
