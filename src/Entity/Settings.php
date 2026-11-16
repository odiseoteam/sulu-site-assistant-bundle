<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

/** Mapping in config/doctrine/Settings.orm.xml. */
class Settings implements SettingsInterface
{
    protected ?int $id = null;

    protected string $webspaceKey = '';

    protected bool $enabled = true;

    protected ?string $turnModel = null;

    protected ?string $turnEffort = null;

    protected ?string $memoryModel = null;

    protected ?string $anthropicApiKey = null;

    protected ?string $openaiApiKey = null;

    protected ?float $sessionBudgetUsd = null;

    protected ?float $dailyBudgetUsd = null;

    protected ?float $monthlyBudgetUsd = null;

    protected ?string $brandName = null;

    protected ?string $assistantName = null;

    protected ?string $brandVoice = null;

    protected ?bool $memoryEnabled = null;

    protected ?int $memoryRetentionDays = null;

    /** @var array<string, array{welcome_title?: ?string, welcome_body?: ?string, starter_chips?: list<string>}> */
    protected array $texts = [];

    protected ?string $updatedBy = null;

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

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }

    public function setWebspaceKey(string $webspaceKey): void
    {
        $this->webspaceKey = $webspaceKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getTurnModel(): ?string
    {
        return $this->turnModel;
    }

    public function setTurnModel(?string $model): void
    {
        $this->turnModel = $model;
    }

    public function getTurnEffort(): ?string
    {
        return $this->turnEffort;
    }

    public function setTurnEffort(?string $effort): void
    {
        $this->turnEffort = $effort;
    }

    public function getMemoryModel(): ?string
    {
        return $this->memoryModel;
    }

    public function setMemoryModel(?string $model): void
    {
        $this->memoryModel = $model;
    }

    public function getAnthropicApiKey(): ?string
    {
        return $this->anthropicApiKey;
    }

    public function setAnthropicApiKey(?string $apiKey): void
    {
        $this->anthropicApiKey = $apiKey;
    }

    public function getOpenaiApiKey(): ?string
    {
        return $this->openaiApiKey;
    }

    public function setOpenaiApiKey(?string $apiKey): void
    {
        $this->openaiApiKey = $apiKey;
    }

    public function getSessionBudgetUsd(): ?float
    {
        return $this->sessionBudgetUsd;
    }

    public function setSessionBudgetUsd(?float $usd): void
    {
        $this->sessionBudgetUsd = $usd;
    }

    public function getDailyBudgetUsd(): ?float
    {
        return $this->dailyBudgetUsd;
    }

    public function setDailyBudgetUsd(?float $usd): void
    {
        $this->dailyBudgetUsd = $usd;
    }

    public function getMonthlyBudgetUsd(): ?float
    {
        return $this->monthlyBudgetUsd;
    }

    public function setMonthlyBudgetUsd(?float $usd): void
    {
        $this->monthlyBudgetUsd = $usd;
    }

    public function getBrandName(): ?string
    {
        return $this->brandName;
    }

    public function setBrandName(?string $name): void
    {
        $this->brandName = $name;
    }

    public function getAssistantName(): ?string
    {
        return $this->assistantName;
    }

    public function setAssistantName(?string $name): void
    {
        $this->assistantName = $name;
    }

    public function getBrandVoice(): ?string
    {
        return $this->brandVoice;
    }

    public function setBrandVoice(?string $voice): void
    {
        $this->brandVoice = $voice;
    }

    public function isMemoryEnabled(): ?bool
    {
        return $this->memoryEnabled;
    }

    public function setMemoryEnabled(?bool $enabled): void
    {
        $this->memoryEnabled = $enabled;
    }

    public function getMemoryRetentionDays(): ?int
    {
        return $this->memoryRetentionDays;
    }

    public function setMemoryRetentionDays(?int $days): void
    {
        $this->memoryRetentionDays = $days;
    }

    public function getTexts(string $locale): array
    {
        return $this->texts[$locale] ?? [];
    }

    public function setTexts(string $locale, array $texts): void
    {
        $this->texts[$locale] = $texts;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $user): void
    {
        $this->updatedBy = $user;
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
