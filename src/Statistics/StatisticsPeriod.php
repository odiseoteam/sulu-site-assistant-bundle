<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Statistics;

/** Whole days, `to` included. Built from a preset (`?period=`) or a range (`?from=&to=`). */
final class StatisticsPeriod
{
    public const PRESETS = ['7d', '30d', 'this_month', 'last_month'];

    public const DEFAULT = '30d';

    private function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly ?string $preset,
    ) {
    }

    public static function fromQuery(?string $preset, ?string $from, ?string $to, \DateTimeImmutable $today): self
    {
        $start = self::day($from);
        $end = self::day($to);
        if (null !== $start && null !== $end && $start <= $end) {
            return new self($start, $end, null);
        }

        return self::preset(\in_array($preset, self::PRESETS, true) ? $preset : self::DEFAULT, $today->setTime(0, 0));
    }

    /**
     * The query string that rebuilds this period.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return null !== $this->preset
            ? ['period' => $this->preset]
            : ['from' => $this->from->format('Y-m-d'), 'to' => $this->to->format('Y-m-d')];
    }

    /** Start of the day after `to`, for a half-open range. */
    public function end(): \DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    private static function preset(string $preset, \DateTimeImmutable $today): self
    {
        return match ($preset) {
            '7d' => new self($today->modify('-6 days'), $today, $preset),
            'this_month' => new self($today->modify('first day of this month'), $today, $preset),
            'last_month' => new self($today->modify('first day of last month'), $today->modify('last day of last month'), $preset),
            default => new self($today->modify('-29 days'), $today, $preset),
        };
    }

    private static function day(?string $value): ?\DateTimeImmutable
    {
        $day = null === $value ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $day ? null : $day;
    }
}
