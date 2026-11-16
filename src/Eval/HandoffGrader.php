<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Eval;

use Odiseo\AiConversationalAgentBundle\Eval\EvalCase;
use Odiseo\AiConversationalAgentBundle\Eval\Grader\GraderInterface;
use Odiseo\AiConversationalAgentBundle\Eval\TurnRecording;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Capability\HandoffCapability;

/**
 * The case a conversation left: `handoff: {topic, path}` asks for a request on that topic and a
 * card that registered it on that path. Either key can be left out.
 */
final class HandoffGrader implements GraderInterface
{
    public function keys(): array
    {
        return ['handoff'];
    }

    public function grade(EvalCase $case, TurnRecording $recording): array
    {
        $expected = Scalar::keyed($case->expected['handoff'] ?? null);
        $failures = [];

        if (isset($expected['topic'])) {
            $topics = [];
            foreach ($recording->toolCalls as $call) {
                if (HandoffCapability::REQUEST === $call['tool']) {
                    $topics[] = Scalar::string($call['input']['topic'] ?? null);
                }
            }
            if (!\in_array(Scalar::string($expected['topic']), $topics, true)) {
                $failures[] = \sprintf('expected a handoff on %s; got %s', Scalar::string($expected['topic']), [] === $topics ? 'none' : \implode(', ', $topics));
            }
        }

        if (isset($expected['path'])) {
            $card = null;
            foreach ($recording->payloads as $shown) {
                if (HandoffCapability::COMPONENT === $shown['component']) {
                    $card = $shown['payload'];
                }
            }
            $path = null === $card || 'failed' === ($card['status'] ?? null) || !isset($card['reference']) ? null : Scalar::nullableString($card['path'] ?? null);
            if (Scalar::string($expected['path']) !== $path) {
                $failures[] = \sprintf('expected a registered case on %s; the card showed %s', Scalar::string($expected['path']), $path ?? 'no registered case');
            }
        }

        return $failures;
    }
}
