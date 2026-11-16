<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;
use Odiseo\SuluSiteAssistantBundle\Repository\SettingsRepository;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What the admin saved for the webspace the assistant works for. Read once per webspace and
 * reset between a worker's requests.
 */
final class WebspaceSettings implements ResetInterface
{
    /** @var array<string, ?SettingsInterface> */
    private array $settings = [];

    public function __construct(
        private readonly CurrentWebspace $webspace,
        private readonly SettingsRepository $repository,
    ) {
    }

    public function webspaceKey(): ?string
    {
        return $this->webspace->key();
    }

    public function settings(): ?SettingsInterface
    {
        $key = $this->webspace->key();

        return null === $key ? null : $this->settingsFor($key);
    }

    public function settingsFor(string $webspaceKey): ?SettingsInterface
    {
        if (!\array_key_exists($webspaceKey, $this->settings)) {
            try {
                $this->settings[$webspaceKey] = $this->repository->findOneByWebspace($webspaceKey);
            } catch (\Throwable) {
                // Before the migrations ran there is nothing saved, and the configuration applies.
                $this->settings[$webspaceKey] = null;
            }
        }

        return $this->settings[$webspaceKey];
    }

    /** Off only when the webspace's settings say so. */
    public function enabled(): bool
    {
        return $this->settings()?->isEnabled() ?? true;
    }

    public function reset(): void
    {
        $this->settings = [];
    }
}
