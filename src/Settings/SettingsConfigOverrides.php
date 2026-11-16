<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\AiConversationalAgentBundle\Config\ConfigOverridesInterface;

/**
 * The webspace's saved settings as changes to the core's: models and effort, memory, budgets and
 * the voice. A model outside ModelChoices is left out, so a value the bundle no longer offers
 * falls back to the configuration.
 */
final class SettingsConfigOverrides implements ConfigOverridesInterface
{
    public function __construct(private readonly WebspaceSettings $settings)
    {
    }

    public function changes(): array
    {
        $settings = $this->settings->settings();
        if (null === $settings) {
            return [];
        }

        $changes = \array_filter([
            'brandName' => $settings->getBrandName(),
            'assistantName' => $settings->getAssistantName(),
            'brandVoice' => $settings->getBrandVoice(),
        ], static fn (?string $value): bool => null !== $value && '' !== \trim($value));

        $turn = $settings->getTurnModel();
        if (null !== $turn && null !== $platform = ModelChoices::platformOf($turn)) {
            $changes['model'] = $turn;
            $changes['platform'] = $platform;
        }
        $effort = $settings->getTurnEffort();
        if (null !== $effort && \in_array($effort, ModelChoices::EFFORTS, true)) {
            $changes['thinkingEffort'] = $effort;
        }
        $memory = $settings->getMemoryModel();
        if (null !== $memory && null !== $platform = ModelChoices::platformOf($memory)) {
            $changes['memoryModel'] = $memory;
            $changes['memoryPlatform'] = $platform;
        }
        if (null !== $enabled = $settings->isMemoryEnabled()) {
            $changes['enableMemory'] = $enabled;
        }
        if (null !== $days = $settings->getMemoryRetentionDays()) {
            $changes['memoryRetentionDays'] = $days;
        }
        if (null !== $usd = $settings->getSessionBudgetUsd()) {
            $changes['sessionBudgetUsd'] = $usd;
        }
        if (null !== $usd = $settings->getDailyBudgetUsd()) {
            $changes['scopeDailyBudgetUsd'] = $usd;
        }
        if (null !== $usd = $settings->getMonthlyBudgetUsd()) {
            $changes['scopeMonthlyBudgetUsd'] = $usd;
        }

        return $changes;
    }
}
