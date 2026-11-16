<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Entity;

/**
 * What the admin saves for one webspace. A null field keeps the configuration's value; the
 * texts the widget shows are kept per locale.
 */
interface SettingsInterface
{
    public function getId(): ?int;

    public function getWebspaceKey(): string;

    public function setWebspaceKey(string $webspaceKey): void;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function getTurnModel(): ?string;

    public function setTurnModel(?string $model): void;

    public function getTurnEffort(): ?string;

    public function setTurnEffort(?string $effort): void;

    public function getMemoryModel(): ?string;

    public function setMemoryModel(?string $model): void;

    /** Stored encrypted; only SettingsApiKeys decrypts it, to call a model. */
    public function getAnthropicApiKey(): ?string;

    public function setAnthropicApiKey(?string $apiKey): void;

    public function getOpenaiApiKey(): ?string;

    public function setOpenaiApiKey(?string $apiKey): void;

    public function getSessionBudgetUsd(): ?float;

    public function setSessionBudgetUsd(?float $usd): void;

    public function getDailyBudgetUsd(): ?float;

    public function setDailyBudgetUsd(?float $usd): void;

    public function getMonthlyBudgetUsd(): ?float;

    public function setMonthlyBudgetUsd(?float $usd): void;

    public function getBrandName(): ?string;

    public function setBrandName(?string $name): void;

    public function getAssistantName(): ?string;

    public function setAssistantName(?string $name): void;

    public function getBrandVoice(): ?string;

    public function setBrandVoice(?string $voice): void;

    public function isMemoryEnabled(): ?bool;

    public function setMemoryEnabled(?bool $enabled): void;

    public function getMemoryRetentionDays(): ?int;

    public function setMemoryRetentionDays(?int $days): void;

    /** @return array{welcome_title?: ?string, welcome_body?: ?string, starter_chips?: list<string>} */
    public function getTexts(string $locale): array;

    /** @param array{welcome_title?: ?string, welcome_body?: ?string, starter_chips?: list<string>} $texts */
    public function setTexts(string $locale, array $texts): void;

    public function getUpdatedBy(): ?string;

    public function setUpdatedBy(?string $user): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void;
}
