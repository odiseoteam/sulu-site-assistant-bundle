<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

/** Mapping in config/doctrine/Handoff.orm.xml. A site extends it through `objects.handoff.model`. */
class Handoff implements HandoffInterface
{
    protected ?int $id = null;

    protected ?ConversationInterface $conversation = null;

    protected ?string $webspaceKey = null;

    protected ?string $locale = null;

    protected string $reference = '';

    protected string $topic = '';

    protected string $category = '';

    protected string $summary = '';

    protected ?string $contactName = null;

    protected ?string $contactEmail = null;

    protected ?string $contactPhone = null;

    protected ?string $path = null;

    protected ?string $page = null;

    protected string $state = self::STATE_REQUESTED;

    protected ?\DateTimeImmutable $notifiedAt = null;

    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?ConversationInterface
    {
        return $this->conversation;
    }

    public function setConversation(?ConversationInterface $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function getWebspaceKey(): ?string
    {
        return $this->webspaceKey;
    }

    public function setWebspaceKey(?string $webspaceKey): void
    {
        $this->webspaceKey = $webspaceKey;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): void
    {
        $this->topic = $topic;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $name): void
    {
        $this->contactName = $name;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $email): void
    {
        $this->contactEmail = $email;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $phone): void
    {
        $this->contactPhone = $phone;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(?string $page): void
    {
        $this->page = $page;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function isOpen(): bool
    {
        return \in_array($this->state, self::OPEN_STATES, true);
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): void
    {
        $this->notifiedAt = $notifiedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
