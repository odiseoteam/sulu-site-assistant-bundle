<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Eval\EvalCase;
use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EvalCommandTest extends IntegrationTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = \sys_get_temp_dir() . '/site-assistant-eval-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->dir);

        parent::tearDown();
    }

    public function testAHandoffCaseRunsAsAVisitorAndLeavesNothingBehind(): void
    {
        \file_put_contents($this->dir . '/handoff.json', (string) \json_encode([[
            'id' => 'handoff-quote',
            'state' => ['locale' => 'es', 'seen_pages' => ['pages__servicios__es']],
            'turns' => ['¿Cuánto sale un marketplace?'],
            'expected' => ['calls_tool' => ['request_handoff'], 'handoff' => ['topic' => 'quote']],
        ]]));
        $this->script(
            FakeProvider::toolCall('request_handoff', ['topic' => 'quote', 'summary' => 'Quiere un marketplace.'], 'tu-1'),
            FakeProvider::toolCall('present_handoff', ['opening' => ''], 'tu-2'),
        );

        $tester = new CommandTester((new Application($this->client->getKernel()))->find('agent:eval'));
        $tester->execute(['--dir' => $this->dir, '--output' => $this->dir . '/run.json']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('1 passed, 0 failed', $tester->getDisplay());
        self::assertSame(0, $this->entityManager()->getRepository(HandoffInterface::class)->count([]), 'the case ran in a transaction that was rolled back');
    }

    public function testTheTestApplicationsCasesAreValid(): void
    {
        foreach (\glob(\dirname(__DIR__) . '/Application/evals/*.json') ?: [] as $file) {
            $rows = \json_decode((string) \file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($rows);
            foreach ($rows as $row) {
                self::assertIsArray($row);
                /** @var array<string, mixed> $row */
                self::assertNotSame('', EvalCase::fromArray($row)->id, \basename($file));
            }
        }
    }
}
