<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\AiConversationalAgentBundle\Provider\ApiKeyResolverInterface;
use Odiseo\SuluSiteAssistantBundle\Encryption\ApiKeyEncrypterInterface;

/** The webspace's own key for a platform, decrypted only here; none keeps the configured one. */
final class SettingsApiKeys implements ApiKeyResolverInterface
{
    public function __construct(
        private readonly WebspaceSettings $settings,
        private readonly ApiKeyEncrypterInterface $encrypter,
    ) {
    }

    public function apiKey(string $platform): ?string
    {
        $settings = $this->settings->settings();
        $key = match ($platform) {
            'anthropic' => $settings?->getAnthropicApiKey(),
            'openai' => $settings?->getOpenaiApiKey(),
            default => null,
        };

        return null === $key || '' === $key ? null : $this->encrypter->decrypt($key);
    }
}
