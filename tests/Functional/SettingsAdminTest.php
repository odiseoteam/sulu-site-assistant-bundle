<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

use Odiseo\SuluSiteAssistantBundle\Encryption\SodiumApiKeyEncrypter;
use Odiseo\SuluSiteAssistantBundle\Entity\Settings;

final class SettingsAdminTest extends AdminTestCase
{
    public function testTheListHasEveryWebspace(): void
    {
        $list = $this->json('GET', '/admin/api/site-assistant/settings.json');

        self::assertSame([['id' => 'example', 'webspaceName' => 'Example', 'enabled' => true, 'assistantName' => null, 'turnModel' => null, 'updatedAt' => null, 'updatedBy' => null]], $list['_embedded']['site_assistant_settings']);
    }

    public function testNothingSavedShowsTheConfiguredValuesAsDefaults(): void
    {
        $settings = $this->json('GET', '/admin/api/site-assistant/settings/example.json?locale=en');

        self::assertTrue($settings['enabled']);
        self::assertNull($settings['turnModel']);
        self::assertSame('claude-haiku-5-5', $settings['defaults']['turnModel']);
        self::assertSame('Example', $settings['defaults']['brandName']);
    }

    public function testSavingKeepsTheTextsPerLocaleAndTheKeyEncrypted(): void
    {
        $this->json('PUT', '/admin/api/site-assistant/settings/example.json?locale=es', [
            'enabled' => true,
            'turnModel' => 'claude-sonnet-5-5',
            'turnEffort' => 'medium',
            'dailyBudgetUsd' => '5',
            'memoryEnabled' => 'off',
            'assistantName' => 'Odi',
            'anthropicApiKey' => 'sk-ant-secret-1234',
            'welcomeTitle' => '¡Hola!',
            'starterChips' => "¿Qué hacen?\n\n¿Cómo trabajan?",
        ]);

        $es = $this->json('GET', '/admin/api/site-assistant/settings/example.json?locale=es');
        $en = $this->json('GET', '/admin/api/site-assistant/settings/example.json?locale=en');

        self::assertSame('claude-sonnet-5-5', $es['turnModel']);
        self::assertSame(5.0, $es['dailyBudgetUsd']);
        self::assertSame('off', $es['memoryEnabled']);
        self::assertSame('¡Hola!', $es['welcomeTitle']);
        self::assertSame("¿Qué hacen?\n¿Cómo trabajan?", $es['starterChips']);
        self::assertNull($en['welcomeTitle'], 'the texts belong to the locale they were saved in');
        self::assertSame('', $es['anthropicApiKey'], 'a key is never sent back');
        self::assertSame('…1234', $es['anthropicApiKeyStored']);

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(Settings::class)->findOneBy(['webspaceKey' => 'example']);
        self::assertStringStartsWith(SodiumApiKeyEncrypter::PREFIX, (string) $stored?->getAnthropicApiKey());
        self::assertSame('test', $stored?->getUpdatedBy());
    }

    public function testAnEmptyKeyKeepsTheStoredOneAndClearRemovesIt(): void
    {
        $this->json('PUT', '/admin/api/site-assistant/settings/example.json', ['anthropicApiKey' => 'sk-ant-keep-9999']);
        $this->json('PUT', '/admin/api/site-assistant/settings/example.json', ['anthropicApiKey' => '']);
        self::assertSame('…9999', $this->json('GET', '/admin/api/site-assistant/settings/example.json')['anthropicApiKeyStored']);

        $this->json('PUT', '/admin/api/site-assistant/settings/example.json', ['anthropicApiKeyClear' => true]);
        self::assertSame('', $this->json('GET', '/admin/api/site-assistant/settings/example.json')['anthropicApiKeyStored']);
    }

    public function testInvalidValuesAreRefused(): void
    {
        $response = $this->json('PUT', '/admin/api/site-assistant/settings/example.json', ['turnModel' => 'gpt-2', 'dailyBudgetUsd' => -1, 'memoryRetentionDays' => 0], 400);

        self::assertCount(3, $response['errors']);
        $this->json('GET', '/admin/api/site-assistant/settings/unknown.json', status: 404);
    }
}
