<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

/** A case the assistant passed to a person on the team. */
interface HandoffInterface
{
    public const STATE_REQUESTED = 'requested';

    public const STATE_TAKEN = 'taken';

    public const STATE_RESOLVED = 'resolved';

    public const STATE_FAILED = 'failed';

    public const STATES = [self::STATE_REQUESTED, self::STATE_TAKEN, self::STATE_RESOLVED, self::STATE_FAILED];

    public const OPEN_STATES = [self::STATE_REQUESTED, self::STATE_TAKEN];

    public function getId(): ?int;

    public function getConversation(): ?ConversationInterface;

    public function setConversation(?ConversationInterface $conversation): void;

    public function getWebspaceKey(): ?string;

    public function setWebspaceKey(?string $webspaceKey): void;

    public function getLocale(): ?string;

    public function setLocale(?string $locale): void;

    public function getReference(): string;

    public function setReference(string $reference): void;

    public function getTopic(): string;

    public function setTopic(string $topic): void;

    public function getCategory(): string;

    public function setCategory(string $category): void;

    public function getSummary(): string;

    public function setSummary(string $summary): void;

    public function getContactName(): ?string;

    public function setContactName(?string $name): void;

    public function getContactEmail(): ?string;

    public function setContactEmail(?string $email): void;

    public function getContactPhone(): ?string;

    public function setContactPhone(?string $phone): void;

    /** How the case goes on: one of the configured handoff paths, once opened. */
    public function getPath(): ?string;

    public function setPath(?string $path): void;

    /** Where the visitor was when the case opened. */
    public function getPage(): ?string;

    public function setPage(?string $page): void;

    public function getState(): string;

    public function setState(string $state): void;

    public function isOpen(): bool;

    public function getNotifiedAt(): ?\DateTimeImmutable;

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void;
}
