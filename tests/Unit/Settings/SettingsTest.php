<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Settings;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Session\CurrentSession;
use Odiseo\SuluSiteAssistantBundle\Encryption\SodiumApiKeyEncrypter;
use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Entity\Settings;
use Odiseo\SuluSiteAssistantBundle\Repository\SettingsRepository;
use Odiseo\SuluSiteAssistantBundle\Settings\ModelChoices;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsApiKeys;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsConfigOverrides;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsMemoryRetention;
use Odiseo\SuluSiteAssistantBundle\Settings\WebspaceSettings;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class SettingsTest extends TestCase
{
    public function testNothingSavedChangesNothing(): void
    {
        self::assertSame([], (new SettingsConfigOverrides($this->webspaceSettings(null)))->changes());
        self::assertTrue($this->webspaceSettings(null)->enabled());
    }

    public function testTheSavedSettingsBecomeTheCoresChanges(): void
    {
        $settings = new Settings();
        $settings->setTurnModel('gpt-5.6-luna');
        $settings->setTurnEffort('high');
        $settings->setMemoryModel('unknown-model');
        $settings->setBrandName('Odiseo');
        $settings->setAssistantName(' ');
        $settings->setMemoryEnabled(false);
        $settings->setMemoryRetentionDays(30);
        $settings->setSessionBudgetUsd(0.25);
        $settings->setDailyBudgetUsd(5.0);
        $settings->setMonthlyBudgetUsd(100.0);

        self::assertSame([
            'brandName' => 'Odiseo',
            'model' => 'gpt-5.6-luna',
            'platform' => 'openai',
            'thinkingEffort' => 'high',
            'enableMemory' => false,
            'memoryRetentionDays' => 30,
            'sessionBudgetUsd' => 0.25,
            'scopeDailyBudgetUsd' => 5.0,
            'scopeMonthlyBudgetUsd' => 100.0,
        ], (new SettingsConfigOverrides($this->webspaceSettings($settings)))->changes());
    }

    public function testAKeyIsDecryptedOnlyForThePlatformThatAsks(): void
    {
        $encrypter = new SodiumApiKeyEncrypter('secret');
        $settings = new Settings();
        $settings->setAnthropicApiKey($encrypter->encrypt('sk-ant-1'));
        $keys = new SettingsApiKeys($this->webspaceSettings($settings), $encrypter);

        self::assertSame('sk-ant-1', $keys->apiKey('anthropic'));
        self::assertNull($keys->apiKey('openai'));
        self::assertNull($keys->apiKey('mistral'));
    }

    public function testAWebspaceTurnedOffIsOff(): void
    {
        $settings = new Settings();
        $settings->setEnabled(false);

        self::assertFalse($this->webspaceSettings($settings)->enabled());
    }

    public function testPruningKeepsTheLongestRetention(): void
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findMemoryRetentionDays')->willReturn([30]);
        $retention = new SettingsMemoryRetention($repository, Site::locales($this));

        self::assertSame(30, $retention->retentionDays(10), 'the only webspace set its own');
        self::assertSame(30, $retention->retentionDays(null));
    }

    public function testModelsAreDescribedWithTheirProvider(): void
    {
        self::assertSame('Claude Haiku 5.5 (Anthropic)', ModelChoices::describe('claude-haiku-5-5'));
        self::assertSame('claude-opus-9 (Anthropic)', ModelChoices::describe('claude-opus-9'));
        self::assertSame('gpt-6 (OpenAI)', ModelChoices::describe('gpt-6'));
        self::assertSame('anthropic', ModelChoices::platformOf('claude-sonnet-5-5'));
    }

    private function webspaceSettings(?Settings $settings): WebspaceSettings
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findOneByWebspace')->willReturn($settings);

        return new WebspaceSettings(
            new CurrentWebspace(Site::locales($this), new RequestStack(), new CurrentSession(), $this->createStub(EntityManagerInterface::class), Conversation::class),
            $repository,
        );
    }
}
