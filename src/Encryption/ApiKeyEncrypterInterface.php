<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Encryption;

/** API keys are stored encrypted and only decrypted to call a model. Both ways are idempotent. */
interface ApiKeyEncrypterInterface
{
    public function encrypt(#[\SensitiveParameter] string $apiKey): string;

    public function decrypt(string $apiKey): string;
}
