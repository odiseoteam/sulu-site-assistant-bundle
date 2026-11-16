<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Encryption;

use Odiseo\SuluSiteAssistantBundle\Encryption\SodiumApiKeyEncrypter;
use PHPUnit\Framework\TestCase;

final class SodiumApiKeyEncrypterTest extends TestCase
{
    public function testAKeyComesBackAsItWentInAndIsNeverStoredInClear(): void
    {
        $encrypter = new SodiumApiKeyEncrypter('secret');
        $encrypted = $encrypter->encrypt('sk-ant-123456');

        self::assertStringStartsWith(SodiumApiKeyEncrypter::PREFIX, $encrypted);
        self::assertStringNotContainsString('sk-ant', $encrypted);
        self::assertSame('sk-ant-123456', $encrypter->decrypt($encrypted));
        self::assertNotSame($encrypted, $encrypter->encrypt('sk-ant-123456'), 'a fresh nonce each time');
    }

    public function testBothWaysAreIdempotent(): void
    {
        $encrypter = new SodiumApiKeyEncrypter('secret');
        $encrypted = $encrypter->encrypt('sk-ant-123456');

        self::assertSame($encrypted, $encrypter->encrypt($encrypted));
        self::assertSame('sk-plain', $encrypter->decrypt('sk-plain'));
        self::assertSame('', $encrypter->encrypt(''));
    }

    public function testAnotherSecretCannotReadIt(): void
    {
        $encrypted = (new SodiumApiKeyEncrypter('secret'))->encrypt('sk-ant-123456');

        self::assertSame('', (new SodiumApiKeyEncrypter('another'))->decrypt($encrypted));
        self::assertSame('', (new SodiumApiKeyEncrypter('secret'))->decrypt(SodiumApiKeyEncrypter::PREFIX . 'garbage'));
    }
}
