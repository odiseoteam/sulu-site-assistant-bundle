<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use CmsIg\Seal\EngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Provider\Request\TurnRequest;
use Odiseo\AiConversationalAgentBundle\Provider\Response\ProviderResponse;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentSection;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentReindexProvider;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Tests\Application\Provider\ScriptedModelProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The website side of a site: the agent's routes, a visitor's cookie, the pages in the index.
 * Each test scripts the model calls it expects and runs in a transaction that is rolled back.
 */
abstract class IntegrationTestCase extends SuluTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createWebsiteClient();
        $this->client->disableReboot();
        ScriptedModelProvider::reset();
        $this->index(
            self::page('services', 'Sylius development', 'service', 'We build marketplaces and B2B stores on Sylius.', '/sylius-development'),
            self::page('symfony', 'Symfony development', 'service', 'Custom applications and APIs on Symfony.', '/symfony-development'),
            self::page('servicios', 'Desarrollo Sylius', 'service', 'Construimos marketplaces en Sylius.', '/desarrollo-sylius', 'es'),
        );
    }

    protected function tearDown(): void
    {
        ScriptedModelProvider::reset();

        parent::tearDown();
    }

    protected static function getSuluContext(): string
    {
        return SuluKernel::CONTEXT_WEBSITE;
    }

    protected function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function script(ProviderResponse ...$responses): void
    {
        ScriptedModelProvider::queue(...$responses);
    }

    /** What the model received on its last call in the last request. */
    protected function lastModelRequest(): ?TurnRequest
    {
        /** @var ScriptedModelProvider $provider */
        $provider = static::getContainer()->get(ScriptedModelProvider::class);

        return $provider->lastRequest();
    }

    /** The cached part of the prompt the model was given on its last call: the same bytes on every turn. */
    protected function lastSystemPrompt(): string
    {
        $system = $this->lastModelRequest()->system ?? [];

        return $system[0]->text ?? '';
    }

    /** The session context the model was given on its last call, after the cache breakpoint. */
    protected function lastContextBlock(): string
    {
        $system = $this->lastModelRequest()->system ?? [];

        return \count($system) > 1 ? $system[\count($system) - 1]->text : '';
    }

    protected function index(ContentDocument ...$documents): void
    {
        /** @var EngineInterface $engine */
        $engine = static::getContainer()->get('cmsig_seal.engine.default');
        if (!$engine->existIndex(ContentReindexProvider::INDEX)) {
            $engine->createIndex(ContentReindexProvider::INDEX);
        }
        foreach ($documents as $document) {
            $engine->saveDocument(ContentReindexProvider::INDEX, $document->toDocument());
        }
    }

    protected static function page(string $id, string $title, string $template, string $text, string $url, string $locale = 'en'): ContentDocument
    {
        return new ContentDocument(
            ContentDocument::identifier('pages', $id, $locale),
            'pages',
            $id,
            $locale,
            ['example'],
            $template,
            $title,
            $text,
            $url,
            [new ContentSection(null, $text), new ContentSection('How we work', 'A discovery call, a proposal, then two-week sprints.')],
        );
    }

    /** @param array<string, string> $headers */
    protected function startSession(array $headers = []): string
    {
        $this->client->request('POST', '/agent/session', server: $this->server($headers));
        self::assertResponseStatusCodeSame(201);

        /** @var array{session_id: string} $body */
        $body = \json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $body['session_id'];
    }

    /**
     * One turn, returned as the events it streamed.
     *
     * @param array<string, mixed> $page
     * @param array<string, string> $headers
     *
     * @return list<array{event: string, data: array<string, mixed>}>
     */
    protected function chat(string $sessionId, string $message, array $page = ['url' => '/en/sylius-development', 'title' => 'Sylius development'], array $headers = []): array
    {
        $this->client->request('POST', '/agent/chat', server: $this->server($headers + [
            'X-Session-Id' => $sessionId,
            'Content-Type' => 'application/json',
        ]), content: \json_encode(['message' => $message, 'page' => $page], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return self::events((string) $this->client->getInternalResponse()->getContent());
    }

    /**
     * @param list<array{event: string, data: array<string, mixed>}> $events
     *
     * @return list<array<string, mixed>>
     */
    protected static function ofType(array $events, string $type): array
    {
        return \array_values(\array_map(
            static fn (array $event): array => $event['data'],
            \array_filter($events, static fn (array $event): bool => $type === $event['event']),
        ));
    }

    /**
     * The cards of a turn, by component.
     *
     * @param list<array{event: string, data: array<string, mixed>}> $events
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function cards(array $events): array
    {
        $cards = [];
        foreach (self::ofType($events, 'ui') as $ui) {
            /** @var array<string, mixed> $payload */
            $payload = $ui['payload'];
            $cards[\is_string($ui['component'] ?? null) ? $ui['component'] : ''] = $payload;
        }

        return $cards;
    }

    protected function conversation(string $sessionId): ConversationInterface
    {
        $this->entityManager()->clear();
        $conversation = $this->entityManager()->getRepository(ConversationInterface::class)->findOneBy(['sessionId' => $sessionId]);
        self::assertInstanceOf(ConversationInterface::class, $conversation, 'The conversation was not saved.');

        return $conversation;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function server(array $headers): array
    {
        $server = ['HTTP_X_AGENT_WEBSPACE' => 'example', 'HTTP_X_AGENT_LOCALE' => 'en'];
        foreach ($headers as $name => $value) {
            $key = \strtoupper(\str_replace('-', '_', $name));
            $server['Content-Type' === $name ? 'CONTENT_TYPE' : 'HTTP_' . $key] = $value;
        }

        return $server;
    }

    /** @return list<array{event: string, data: array<string, mixed>}> */
    private static function events(string $stream): array
    {
        $events = [];
        foreach (\preg_split('/\n\n/', \trim($stream)) ?: [] as $frame) {
            if (1 === \preg_match('/^event: (.+)\ndata: (.*)$/s', $frame, $match)) {
                /** @var array<string, mixed> $data */
                $data = \json_decode($match[2], true, flags: \JSON_THROW_ON_ERROR);
                $events[] = ['event' => $match[1], 'data' => $data];
            }
        }

        return $events;
    }
}
