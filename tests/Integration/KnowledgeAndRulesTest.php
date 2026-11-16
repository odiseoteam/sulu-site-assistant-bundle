<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;
use Odiseo\AiConversationalAgentBundle\Knowledge\VersionedKnowledgeSource;
use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;
use Odiseo\AiConversationalAgentBundle\Rule\VersionedRuleSource;

final class KnowledgeAndRulesTest extends IntegrationTestCase
{
    public function testTheKnowledgeFilesAnswerTheOrganisationsTerms(): void
    {
        $this->script(
            FakeProvider::toolCall('search_knowledge', ['query' => 'payment'], 'tu-1'),
            FakeProvider::text('Monthly, against the hours worked.'),
        );

        $this->chat($this->startSession(), 'How do I pay for a project?');

        self::assertStringContainsString('billed monthly against the hours worked', (string) \json_encode($this->lastModelRequest()?->messages));
    }

    public function testADocumentPublishedInTheAdminIsSearchedToo(): void
    {
        $this->versions()->publish(VersionedKnowledgeSource::KIND, 'hosting', [
            'title' => 'Hosting',
            'kind' => 'policy',
            'when' => '',
            'body' => "# Hosting\n\nWe host every store we build on managed cloud servers.",
            'url' => null,
            'enabled' => true,
        ], 'admin');

        $this->script(
            FakeProvider::toolCall('search_knowledge', ['query' => 'hosting'], 'tu-1'),
            FakeProvider::text('We host them.'),
        );
        $this->chat($this->startSession(), 'Do you host the stores?');

        self::assertStringContainsString('managed cloud servers', (string) \json_encode($this->lastModelRequest()?->messages));
    }

    public function testTheHouseRulesAreInThePromptAndAPublishedOneJoinsThem(): void
    {
        $this->versions()->publish(VersionedRuleSource::KIND, 'no-deadlines', [
            'title' => 'No deadlines',
            'category' => 'style',
            'text' => 'Never say how long a project takes.',
            'enabled' => true,
        ], 'admin');

        $this->script(FakeProvider::text('Hello.'));
        $this->chat($this->startSession(), 'Hello');

        $prompt = $this->lastSystemPrompt();
        self::assertStringContainsString('Do not offer discounts or free work', $prompt);
        self::assertStringContainsString('Never say how long a project takes.', $prompt);
    }

    private function versions(): VersionStoreInterface
    {
        /** @var VersionStoreInterface */
        return static::getContainer()->get(VersionStoreInterface::class);
    }
}
