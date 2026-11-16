<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Eval;

use Odiseo\AiConversationalAgentBundle\Eval\EvalCase;
use Odiseo\AiConversationalAgentBundle\Eval\TurnRecording;
use Odiseo\SuluSiteAssistantBundle\Eval\HandoffGrader;
use PHPUnit\Framework\TestCase;

final class HandoffGraderTest extends TestCase
{
    public function testTheTopicAndTheRegisteredPathAreChecked(): void
    {
        $recording = new TurnRecording();
        $recording->toolCalls[] = ['tool' => 'request_handoff', 'input' => ['topic' => 'quote']];
        $recording->payloads[] = ['component' => 'handoff', 'payload' => ['status' => 'registered', 'reference' => 'W-X', 'path' => 'visitor_writes']];

        self::assertSame([], (new HandoffGrader())->grade($this->case(['topic' => 'quote', 'path' => 'visitor_writes']), $recording));
        self::assertSame(
            ['expected a handoff on person; got quote', 'expected a registered case on team_contact; the card showed visitor_writes'],
            (new HandoffGrader())->grade($this->case(['topic' => 'person', 'path' => 'team_contact']), $recording),
        );
    }

    public function testAFailedCardRegistersNothing(): void
    {
        $recording = new TurnRecording();
        $recording->payloads[] = ['component' => 'handoff', 'payload' => ['status' => 'failed']];

        self::assertSame(['expected a registered case on visitor_writes; the card showed no registered case'], (new HandoffGrader())->grade($this->case(['path' => 'visitor_writes']), $recording));
    }

    /** @param array<string, string> $handoff */
    private function case(array $handoff): EvalCase
    {
        return new EvalCase('case', ['hi'], ['handoff' => $handoff]);
    }
}
