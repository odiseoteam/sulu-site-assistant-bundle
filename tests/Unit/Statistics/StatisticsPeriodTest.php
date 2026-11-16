<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Statistics;

use Odiseo\SuluSiteAssistantBundle\Statistics\Statistics;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsPeriod;
use PHPUnit\Framework\TestCase;

final class StatisticsPeriodTest extends TestCase
{
    public function testPresets(): void
    {
        $today = new \DateTimeImmutable('2026-11-16 15:00');

        $week = StatisticsPeriod::fromQuery('7d', null, null, $today);
        self::assertSame(['2026-11-10', '2026-11-16'], [$week->from->format('Y-m-d'), $week->to->format('Y-m-d')]);
        self::assertSame('2026-11-17', $week->end()->format('Y-m-d'));

        $last = StatisticsPeriod::fromQuery('last_month', null, null, $today);
        self::assertSame(['2026-10-01', '2026-10-31'], [$last->from->format('Y-m-d'), $last->to->format('Y-m-d')]);

        self::assertSame('30d', StatisticsPeriod::fromQuery('forever', null, null, $today)->preset);
    }

    public function testARangeWinsOverThePreset(): void
    {
        $period = StatisticsPeriod::fromQuery('7d', '2026-01-01', '2026-01-31', new \DateTimeImmutable());

        self::assertNull($period->preset);
        self::assertSame(['from' => '2026-01-01', 'to' => '2026-01-31'], $period->toQuery());
        self::assertSame('30d', StatisticsPeriod::fromQuery(null, '2026-02-01', '2026-01-01', new \DateTimeImmutable())->preset, 'a backwards range is ignored');
    }

    public function testRatesOverNoConversationsAreZero(): void
    {
        $statistics = new Statistics(0, 0, 0, 0.0, ['quote' => 2]);

        self::assertSame(0.0, $statistics->rate(2));
        self::assertSame(2, $statistics->handoffs());
        self::assertSame(0.0, $statistics->spendPerConversation());
        self::assertSame(0.5, (new Statistics(4, 2, 1, 2.0))->toArray()['spendPerConversation']);
    }
}
