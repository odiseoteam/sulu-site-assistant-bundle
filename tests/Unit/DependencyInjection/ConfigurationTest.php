<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\DependencyInjection;

use Odiseo\SuluSiteAssistantBundle\DependencyInjection\Compiler\ValidateHandoffRoutesPass;
use Odiseo\SuluSiteAssistantBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ConfigurationTest extends TestCase
{
    public function testTheDefaultsAreNeutral(): void
    {
        $config = $this->process([]);

        self::assertNull($config['webspace']);
        self::assertSame(['content_terms' => [], 'content_cues' => [], 'knowledge_terms' => [], 'knowledge_cues' => []], $config['grounding']);
        self::assertSame([], $config['contact']['links']);
        self::assertFalse($config['access']['restricted']);
        self::assertSame([], $config['handoff']['routes']);
        self::assertSame(['pages', 'articles'], $config['content']['resource_keys']);
        self::assertSame('%kernel.secret%', $config['encryption']['secret']);
    }

    public function testTheAudienceIsTheBundles(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['identity' => ['audience' => 'a shopper']]);
    }

    public function testAContactLinkIsAnHttpsMailTelOrPageLink(): void
    {
        $config = $this->process(['contact' => ['links' => [
            'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/5491100000000'],
            'contact' => ['label' => 'odiseo.contact', 'url' => 'page:6f1c9d2e-4b1a-4c3e-9d6f-1a2b3c4d5e6f'],
        ]]]);
        self::assertCount(2, $config['contact']['links']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['contact' => ['links' => ['web' => ['label' => 'Web', 'url' => 'http://example.com']]]]);
    }

    public function testHoursAreRangesOnKnownDays(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['handoff' => ['hours' => ['monday' => ['09:00-18:00']]]]);
    }

    public function testARangeLooksLikeOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['handoff' => ['hours' => ['mon' => ['9-18']]]]);
    }

    public function testANoticeRecipientIsAnEmail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['handoff' => ['notice' => ['email' => ['recipients' => ['the team']]]]]);
    }

    public function testCoreSectionsAreForwardedAsTheyAre(): void
    {
        $config = $this->process(['budgets' => ['daily_usd' => 5], 'limits' => ['max_search_results' => 4, 'max_components_per_turn' => 2]]);

        self::assertSame(['daily_usd' => 5], $config['budgets']);
        self::assertSame(4, $config['limits']['max_search_results']);
        self::assertSame(2, $config['limits']['max_components_per_turn']);
    }

    public function testAnUnknownPathOrConditionFailsTheBuild(): void
    {
        $container = $this->container(['routes' => [['paths' => ['carrier_pigeon']]]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown handoff path "carrier_pigeon"');
        (new ValidateHandoffRoutesPass())->process($container);
    }

    public function testTeamContactNeedsANotice(): void
    {
        $container = $this->container(['routes' => [['paths' => ['team_contact']]]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a notice');
        (new ValidateHandoffRoutesPass())->process($container);
    }

    public function testKnownPathsAndConditionsPass(): void
    {
        $container = $this->container(['routes' => [['when' => ['hours' => 'open'], 'paths' => ['team_contact']]]]);
        $container->setDefinition('notice', (new Definition(\stdClass::class))->addTag(ValidateHandoffRoutesPass::NOTICE_TAG));

        (new ValidateHandoffRoutesPass())->process($container);
        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        /** @var array<string, mixed> */
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    /** @param array<string, mixed> $handoff */
    private function container(array $handoff): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('odiseo_sulu_site_assistant.handoff', $handoff);
        foreach (['visitor_writes', 'team_contact'] as $path) {
            $container->setDefinition('path.' . $path, (new Definition(\stdClass::class))->addTag(ValidateHandoffRoutesPass::PATH_TAG, ['path' => $path]));
        }
        foreach (['hours', 'contact', 'locale'] as $condition) {
            $container->setDefinition('condition.' . $condition, (new Definition(\stdClass::class))->addTag(ValidateHandoffRoutesPass::CONDITION_TAG, ['condition' => $condition]));
        }

        return $container;
    }
}
