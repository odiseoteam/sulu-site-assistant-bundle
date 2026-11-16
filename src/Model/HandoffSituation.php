<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** What a route's conditions look at. */
final readonly class HandoffSituation
{
    public function __construct(
        public \DateTimeImmutable $now,
        public HandoffSchedule $schedule,
        public HandoffContact $contact,
        public ?string $locale = null,
    ) {
    }
}
