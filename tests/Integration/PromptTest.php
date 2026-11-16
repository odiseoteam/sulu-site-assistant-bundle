<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;

final class PromptTest extends IntegrationTestCase
{
    public function testThePromptIsTheSamesBytesOnEveryTurn(): void
    {
        $this->script(FakeProvider::text('One.'), FakeProvider::text('Two.'));
        $sessionId = $this->startSession();

        $this->chat($sessionId, 'Hi');
        $first = $this->lastSystemPrompt();
        $this->chat($sessionId, 'Hi again', ['url' => '/en/symfony-development', 'title' => 'Symfony']);

        self::assertSame($first, $this->lastSystemPrompt(), 'the page and the turn stay out of the cached prompt');
    }

    public function testItSpeaksForTheSiteAndItsTools(): void
    {
        $this->script(FakeProvider::text('Hello.'));
        $this->chat($this->startSession(), 'Hi');

        $prompt = $this->lastSystemPrompt();
        self::assertStringContainsString('a visitor on the organisation\'s website', $prompt);
        self::assertStringContainsString('what the organisation offers and publishes', $prompt);
        self::assertStringContainsString('putting the visitor in touch with the team', $prompt);
        self::assertStringContainsString('site_data', $prompt);
        foreach (['human-handoff', 'lead-qualification', 'site-discovery', 'service-research', 'memory-personalization'] as $skill) {
            self::assertStringContainsString($skill, $prompt, \sprintf('the %s skill is indexed', $skill));
        }

        $tools = \array_map(static fn ($tool): string => $tool->name, $this->lastModelRequest()->tools ?? []);
        foreach (['search_content', 'get_content', 'search_knowledge', 'request_handoff', 'present_handoff', 'present_pages', 'present_comparison', 'present_guide'] as $tool) {
            self::assertContains($tool, $tools);
        }
    }

    public function testThePageTheVisitorIsOnTravelsWithTheTurn(): void
    {
        $this->script(FakeProvider::text('Hello.'));
        $this->chat($this->startSession(), 'What is this page about?', ['url' => '/en/symfony-development', 'title' => 'Symfony development']);

        $context = $this->lastContextBlock();
        self::assertStringContainsString('/en/symfony-development', $context);
        self::assertStringContainsString('"page_language":"en"', $context);
    }
}
