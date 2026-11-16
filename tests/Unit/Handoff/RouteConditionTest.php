<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Handoff;

use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\ContactCondition;
use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\HoursCondition;
use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\LocaleCondition;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSchedule;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;
use PHPUnit\Framework\TestCase;

final class RouteConditionTest extends TestCase
{
    public function testHours(): void
    {
        $open = $this->situation(new HandoffSchedule(['mon' => ['09:00-18:00']]));

        self::assertTrue((new HoursCondition())->matches('open', $open));
        self::assertFalse((new HoursCondition())->matches('closed', $open));
    }

    public function testContact(): void
    {
        $situation = $this->situation(contact: new HandoffContact(email: 'ana@example.com'));

        self::assertTrue((new ContactCondition())->matches('email', $situation));
        self::assertTrue((new ContactCondition())->matches('any', $situation));
        self::assertFalse((new ContactCondition())->matches('phone', $situation));
        self::assertFalse((new ContactCondition())->matches('nonsense', $situation));
    }

    public function testLocale(): void
    {
        $situation = $this->situation(locale: 'es');

        self::assertTrue((new LocaleCondition())->matches('es', $situation));
        self::assertTrue((new LocaleCondition())->matches(['pt', 'es'], $situation));
        self::assertFalse((new LocaleCondition())->matches('en', $situation));
    }

    private function situation(HandoffSchedule $schedule = new HandoffSchedule(), HandoffContact $contact = new HandoffContact(), ?string $locale = 'en'): HandoffSituation
    {
        return new HandoffSituation(new \DateTimeImmutable('2026-11-16 10:00'), $schedule, $contact, $locale);
    }
}
