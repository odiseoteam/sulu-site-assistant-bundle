<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Application\Provider;

use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;
use Odiseo\AiConversationalAgentBundle\Provider\ModelProviderInterface;
use Odiseo\AiConversationalAgentBundle\Provider\ProviderCapabilities;
use Odiseo\AiConversationalAgentBundle\Provider\Request\TurnRequest;
use Odiseo\AiConversationalAgentBundle\Provider\Response\ProviderResponse;

/**
 * The model of the test application. A test queues the responses it expects; they are kept in
 * a file so they reach the kernel of every request, including a browser test's web server.
 */
final class ScriptedModelProvider implements ModelProviderInterface
{
    private ?FakeProvider $current = null;

    public static function queue(ProviderResponse ...$responses): void
    {
        self::write([...self::read(), ...$responses]);
    }

    public static function reset(): void
    {
        @\unlink(self::file());
    }

    public function capabilities(string $model): ProviderCapabilities
    {
        return (new FakeProvider())->capabilities($model);
    }

    public function stream(TurnRequest $request): iterable
    {
        return $this->next()->stream($request);
    }

    public function complete(TurnRequest $request): ProviderResponse
    {
        return $this->next()->complete($request);
    }

    /** The last request the model got, for a test that checks what was sent. */
    public function lastRequest(): ?TurnRequest
    {
        return $this->current?->lastRequest();
    }

    private function next(): FakeProvider
    {
        $responses = self::read();
        $this->current = new FakeProvider(\array_slice($responses, 0, 1));
        self::write(\array_slice($responses, 1));

        return $this->current;
    }

    /** @return list<ProviderResponse> */
    private static function read(): array
    {
        $raw = @\file_get_contents(self::file());
        $responses = false === $raw ? [] : \unserialize($raw);

        return \is_array($responses) ? \array_values(\array_filter($responses, static fn (mixed $r): bool => $r instanceof ProviderResponse)) : [];
    }

    /** @param list<ProviderResponse> $responses */
    private static function write(array $responses): void
    {
        @\mkdir(\dirname(self::file()), 0777, true);
        \file_put_contents(self::file(), \serialize($responses), \LOCK_EX);
    }

    private static function file(): string
    {
        return \dirname(__DIR__, 3) . '/var/scripted-model.bin';
    }
}
