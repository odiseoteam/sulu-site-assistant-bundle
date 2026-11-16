<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;

final class ChatTurnTest extends IntegrationTestCase
{
    public function testASearchThenAShortlistShowsTheSitesOwnPages(): void
    {
        $this->script(
            FakeProvider::toolCall('search_content', ['query' => 'marketplace'], 'tu-1'),
            FakeProvider::toolCall('present_pages', ['title' => 'Where to start', 'picks' => [
                ['content_id' => 'pages__services__en', 'reason' => 'Marketplaces are what you asked for.'],
                ['content_id' => 'pages__invented__en'],
            ], 'suggestions' => ['Compare them']], 'tu-2'),
            // The invented page comes back to the model as a note, so the turn goes on.
            FakeProvider::text('Those are the closest.'),
        );

        $sessionId = $this->startSession();
        $events = $this->chat($sessionId, 'Can you build a marketplace?');

        self::assertSame(['search_content', 'present_pages'], \array_column(self::ofType($events, 'tool_call'), 'tool'));
        self::assertSame([], self::ofType($events, 'error'));
        $pages = self::cards($events)['pages'] ?? null;
        self::assertIsArray($pages);
        self::assertIsArray($pages['picks']);
        self::assertCount(1, $pages['picks'], 'the invented page never reaches the visitor');
        self::assertSame('Sylius development', $pages['picks'][0]['title']);
        self::assertSame('/en/sylius-development', $pages['picks'][0]['url']);

        $conversation = $this->conversation($sessionId);
        self::assertSame('example', $conversation->getWebspaceKey());
        self::assertSame('en', $conversation->getLocale());
        self::assertSame(1, $conversation->getTurnCount());
        self::assertSame('Can you build a marketplace?', $conversation->getFirstMessage());
        self::assertSame('/en/sylius-development', $conversation->getLastPage());
    }

    public function testTheVisitorsLanguagePicksThePages(): void
    {
        $this->script(
            FakeProvider::toolCall('search_content', ['query' => 'marketplaces'], 'tu-1'),
            FakeProvider::text('Sí, hacemos marketplaces.'),
        );

        $sessionId = $this->startSession(['X-Agent-Locale' => 'es']);
        $this->chat($sessionId, '¿Hacen marketplaces?', headers: ['X-Agent-Locale' => 'es']);

        $result = (string) \json_encode($this->lastModelRequest()?->messages);
        self::assertStringContainsString('Desarrollo Sylius', $result);
        self::assertStringNotContainsString('Sylius development', $result);
        self::assertSame('es', $this->conversation($sessionId)->getLocale());
    }

    public function testTheVisitorCookieKeepsTheConversationTheirs(): void
    {
        $this->script(FakeProvider::text('Hello!'));
        $sessionId = $this->startSession();
        $this->chat($sessionId, 'Hi');

        $this->client->getCookieJar()->clear();
        $this->client->request('POST', '/agent/chat', server: [
            'HTTP_X_SESSION_ID' => $sessionId,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_AGENT_WEBSPACE' => 'example',
        ], content: '{"message":"Hi again"}');

        self::assertResponseStatusCodeSame(401, 'another browser cannot pick up the session');
    }

    public function testARetryAnswersTheSameMessageWithoutCountingAnotherTurn(): void
    {
        $this->script(FakeProvider::text('First answer.'), FakeProvider::text('Second answer.'));
        $sessionId = $this->startSession();
        $this->chat($sessionId, 'Hello');

        $this->client->request('POST', '/agent/chat', server: [
            'HTTP_X_SESSION_ID' => $sessionId,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_AGENT_WEBSPACE' => 'example',
        ], content: '{"message":"Hello","retry":true}');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->conversation($sessionId)->getTurnCount());
    }
}
