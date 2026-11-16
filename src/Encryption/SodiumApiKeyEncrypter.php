<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Encryption;

/**
 * Libsodium's secret box, keyed by a hash of the configured secret (`encryption.secret`, the
 * kernel secret by default). Changing the secret leaves the stored keys unreadable: they decrypt
 * to nothing and the configured key applies until they are saved again.
 */
final readonly class SodiumApiKeyEncrypter implements ApiKeyEncrypterInterface
{
    public const PREFIX = 'sa1:';

    private string $key;

    public function __construct(#[\SensitiveParameter] string $secret)
    {
        $this->key = \sodium_crypto_generichash($secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(#[\SensitiveParameter] string $apiKey): string
    {
        if ('' === $apiKey || $this->isEncrypted($apiKey)) {
            return $apiKey;
        }

        $nonce = \random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . \base64_encode($nonce . \sodium_crypto_secretbox($apiKey, $nonce, $this->key));
    }

    public function decrypt(string $apiKey): string
    {
        if (!$this->isEncrypted($apiKey)) {
            return $apiKey;
        }

        $raw = \base64_decode(\substr($apiKey, \strlen(self::PREFIX)), true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $plain = \sodium_crypto_secretbox_open(
            \substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            \substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $plain ? '' : $plain;
    }

    private function isEncrypted(string $value): bool
    {
        return \str_starts_with($value, self::PREFIX);
    }
}
