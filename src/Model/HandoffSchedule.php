<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** Opening hours by day. A schedule with no days is always open. */
final readonly class HandoffSchedule
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @param array<string, list<string>> $days ranges like 10:00-13:00, by lowercase day */
    public function __construct(private array $days = [])
    {
    }

    public function isOpen(\DateTimeImmutable $now): bool
    {
        if ([] === $this->days) {
            return true;
        }

        $time = $now->format('H:i');
        foreach ($this->days[\strtolower($now->format('D'))] ?? [] as $range) {
            [$from, $to] = \explode('-', $range);
            if ($time >= $from && $time < $to) {
                return true;
            }
        }

        return false;
    }
}
