<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Statistics;

/** A period's numbers, over the conversations that started in it. */
final readonly class Statistics
{
    /**
     * @param array<string, int> $handoffsByTopic the period's cases, most frequent first
     * @param list<array{page: string, conversations: int}> $topPages where conversations start most
     */
    public function __construct(
        public int $conversations,
        public int $engaged,
        public int $contactOpened,
        public float $spendUsd,
        public array $handoffsByTopic = [],
        public array $topPages = [],
    ) {
    }

    public function handoffs(): int
    {
        return \array_sum($this->handoffsByTopic);
    }

    public function rate(int $count): float
    {
        return 0 === $this->conversations ? 0.0 : $count / $this->conversations;
    }

    public function spendPerConversation(): float
    {
        return 0 === $this->conversations ? 0.0 : $this->spendUsd / $this->conversations;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'conversations' => $this->conversations,
            'engaged' => $this->engaged,
            'engagedRate' => \round($this->rate($this->engaged), 3),
            'handoffs' => $this->handoffs(),
            'handoffRate' => \round($this->rate($this->handoffs()), 3),
            'handoffsByTopic' => $this->handoffsByTopic,
            'contactOpened' => $this->contactOpened,
            'contactOpenedRate' => \round($this->rate($this->contactOpened), 3),
            'spendUsd' => \round($this->spendUsd, 2),
            'spendPerConversation' => \round($this->spendPerConversation(), 4),
            'topPages' => $this->topPages,
        ];
    }
}
