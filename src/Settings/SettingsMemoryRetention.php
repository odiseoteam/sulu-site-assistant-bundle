<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\AiConversationalAgentBundle\Memory\MemoryRetentionInterface;
use Odiseo\SuluSiteAssistantBundle\Repository\SettingsRepository;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;

/**
 * Each webspace keeps memory for the days its settings say or, without them, the configured
 * ones; pruning keeps the longest, so no webspace loses what it still uses.
 */
final class SettingsMemoryRetention implements MemoryRetentionInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Webspaces $webspaces,
    ) {
    }

    public function retentionDays(?int $configured): ?int
    {
        $days = $this->settings->findMemoryRetentionDays();
        if (\count($days) < \count($this->webspaces->keys())) {
            if (null === $configured) {
                return null;
            }
            $days[] = $configured;
        }

        return [] === $days ? $configured : \max($days);
    }
}
