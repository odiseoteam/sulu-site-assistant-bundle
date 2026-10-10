<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Statistics;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntryInterface;

/**
 * The funnel for a period: conversations started, those that went past one message, handoffs
 * shown and opened, and what the model cost.
 */
final class StatisticsProvider
{
    private const TOP_PAGES = 5;

    /**
     * @param class-string<ConversationInterface> $conversationClass
     * @param class-string<SpendEntryInterface> $spendEntryClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $conversationClass,
        private readonly string $spendEntryClass,
    ) {
    }

    /** @return array<string, mixed> */
    public function provide(int $days): array
    {
        $since = (new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', $days - 1));

        $conversations = $this->count($since);
        $engaged = $this->count($since, 'c.turnCount >= 2');
        $handoffsShown = $this->count($since, 'c.handoffShownAt IS NOT NULL');
        $handoffsClicked = $this->count($since, 'c.handoffClickedAt IS NOT NULL');
        $spend = (float) $this->em->createQueryBuilder()
            ->select('SUM(s.usd)')
            ->from($this->spendEntryClass, 's')
            ->where('s.spentOn >= :since')
            ->setParameter('since', $since, 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'days' => $days,
            'since' => $since->format('Y-m-d'),
            'conversations' => $conversations,
            'engaged' => $engaged,
            'handoffsShown' => $handoffsShown,
            'handoffsClicked' => $handoffsClicked,
            'handoffRate' => $conversations > 0 ? \round($handoffsShown / $conversations, 3) : 0.0,
            'spendUsd' => \round($spend, 2),
            'costPerConversation' => $conversations > 0 ? \round($spend / $conversations, 3) : 0.0,
            'topPages' => $this->topPages($since),
        ];
    }

    private function count(\DateTimeImmutable $since, ?string $condition = null): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from($this->conversationClass, 'c')
            ->where('c.createdAt >= :since')
            ->andWhere('c.turnCount > 0')
            ->setParameter('since', $since, 'datetime_immutable');
        if (null !== $condition) {
            $query->andWhere($condition);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /** @return list<array{page: string, conversations: int}> */
    private function topPages(\DateTimeImmutable $since): array
    {
        /** @var list<array{page: string, conversations: int|string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('c.lastPage AS page', 'COUNT(c.id) AS conversations')
            ->from($this->conversationClass, 'c')
            ->where('c.createdAt >= :since')
            ->andWhere('c.lastPage IS NOT NULL')
            ->groupBy('c.lastPage')
            ->orderBy('conversations', 'DESC')
            ->setMaxResults(self::TOP_PAGES)
            ->setParameter('since', $since, 'datetime_immutable')
            ->getQuery()
            ->getArrayResult();

        return \array_map(static fn (array $row): array => ['page' => $row['page'], 'conversations' => (int) $row['conversations']], $rows);
    }
}
