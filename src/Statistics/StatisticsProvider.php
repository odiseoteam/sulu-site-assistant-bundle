<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Statistics;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntryInterface;

/**
 * Cohort by start date: counts the conversations started in the period (and webspace, if given)
 * and what came of them. A conversation opened without a message is not counted; the spend of
 * every one that started in the period is.
 */
final class StatisticsProvider
{
    private const TOP_PAGES = 5;

    /**
     * @param class-string<ConversationInterface> $conversationClass
     * @param class-string<SpendEntryInterface> $spendEntryClass
     * @param class-string<HandoffInterface> $handoffClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $conversationClass,
        private readonly string $spendEntryClass,
        private readonly string $handoffClass,
    ) {
    }

    public function provide(StatisticsPeriod $period, ?string $webspace = null): Statistics
    {
        /** @var array{conversations: int|string, engaged: int|string|null, opened: int|string|null} $counts */
        $counts = $this->started($this->em->createQueryBuilder()
            ->select('COUNT(c.id) AS conversations')
            ->addSelect('SUM(CASE WHEN c.turnCount >= 2 THEN 1 ELSE 0 END) AS engaged')
            ->addSelect('SUM(CASE WHEN c.contactOpenedAt IS NOT NULL THEN 1 ELSE 0 END) AS opened')
            ->from($this->conversationClass, 'c'), $period, $webspace)
            ->andWhere('c.turnCount > 0')
            ->getQuery()
            ->getSingleResult();

        $spend = $this->em->createQueryBuilder()
            ->select('SUM(e.usd)')
            ->from($this->spendEntryClass, 'e')
            ->where(\sprintf('e.sessionId IN (%s)', $this->em->createQueryBuilder()
                ->select('s.sessionId')
                ->from($this->conversationClass, 's')
                ->where('s.createdAt >= :from AND s.createdAt < :to')
                ->andWhere(null === $webspace ? '1 = 1' : 's.webspaceKey = :webspace')
                ->getDQL()))
            ->setParameter('from', $period->from)
            ->setParameter('to', $period->end());
        if (null !== $webspace) {
            $spend->setParameter('webspace', $webspace);
        }

        $topics = $this->em->createQueryBuilder()
            ->select('h.topic AS topic', 'COUNT(h.id) AS total')
            ->from($this->handoffClass, 'h')
            ->where('h.createdAt >= :from AND h.createdAt < :to')
            ->groupBy('h.topic')
            ->orderBy('total', 'DESC')
            ->setParameter('from', $period->from)
            ->setParameter('to', $period->end());
        if (null !== $webspace) {
            $topics->andWhere('h.webspaceKey = :webspace')->setParameter('webspace', $webspace);
        }
        /** @var list<array{topic: string, total: int|string}> $topicRows */
        $topicRows = $topics->getQuery()->getArrayResult();

        /** @var list<array{page: string, conversations: int|string}> $pages */
        $pages = $this->started($this->em->createQueryBuilder()
            ->select('c.lastPage AS page', 'COUNT(c.id) AS conversations')
            ->from($this->conversationClass, 'c'), $period, $webspace)
            ->andWhere('c.lastPage IS NOT NULL')
            ->groupBy('c.lastPage')
            ->orderBy('conversations', 'DESC')
            ->setMaxResults(self::TOP_PAGES)
            ->getQuery()
            ->getArrayResult();

        return new Statistics(
            (int) $counts['conversations'],
            (int) $counts['engaged'],
            (int) $counts['opened'],
            (float) $spend->getQuery()->getSingleScalarResult(),
            \array_map(intval(...), \array_column($topicRows, 'total', 'topic')),
            \array_map(static fn (array $row): array => ['page' => $row['page'], 'conversations' => (int) $row['conversations']], $pages),
        );
    }

    /** Restricts conversations `c` to the period and webspace. */
    private function started(QueryBuilder $query, StatisticsPeriod $period, ?string $webspace): QueryBuilder
    {
        $query->andWhere('c.createdAt >= :from AND c.createdAt < :to')
            ->setParameter('from', $period->from)
            ->setParameter('to', $period->end());
        if (null !== $webspace) {
            $query->andWhere('c.webspaceKey = :webspace')->setParameter('webspace', $webspace);
        }

        return $query;
    }
}
