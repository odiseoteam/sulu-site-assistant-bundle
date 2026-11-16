<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Model;

use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\ContactPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffCategory;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffMode;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSchedule;
use Odiseo\SuluSiteAssistantBundle\Model\PathChoice;
use PHPUnit\Framework\TestCase;

final class HandoffPolicyTest extends TestCase
{
    public function testTheBaseTopicsComeWithTheBundleAndASiteChangesOrTurnsThemOff(): void
    {
        $policy = HandoffPolicy::fromArray([
            'topics' => [
                'quote' => ['description' => 'A price for a project.'],
                'sensitive' => ['enabled' => false],
                'press' => ['description' => 'A journalist asks.', 'category' => 'other', 'mode' => 'direct'],
            ],
        ]);

        self::assertSame(['person', 'quote', 'support', 'unresolved', 'press'], \array_keys($policy->topics));
        self::assertSame('A price for a project.', $policy->topic('quote')?->description);
        self::assertSame(HandoffCategory::Sale, $policy->topic('quote')->category, 'what the site leaves out keeps the base value');
        self::assertSame(HandoffMode::Offer, $policy->topics['unresolved']->mode);
    }

    public function testANewTopicNeedsEveryField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HandoffPolicy::fromArray(['topics' => ['press' => ['description' => 'A journalist asks.']]]);
    }

    public function testTheMostSpecificRoutesWin(): void
    {
        $policy = HandoffPolicy::fromArray([
            'routes' => [['paths' => ['visitor_writes']]],
            'topics' => ['quote' => ['routes' => [['paths' => ['team_contact'], 'contact' => 'optional']]]],
            'webspaces' => [
                'shop' => ['routes' => [['when' => ['hours' => 'open'], 'paths' => ['visitor_writes', 'team_contact'], 'choice' => 'priority']]],
                'blog' => ['topics' => ['person' => ['enabled' => false]]],
            ],
        ]);

        self::assertSame(['visitor_writes'], $policy->routes('person', null)[0]->paths);
        self::assertSame(['team_contact'], $policy->routes('quote', null)[0]->paths);
        self::assertSame(ContactPolicy::Optional, $policy->routes('quote', null)[0]->contact);
        $shop = $policy->routes('quote', 'shop')[0];
        self::assertSame(['hours' => 'open'], $shop->when);
        self::assertSame(PathChoice::Priority, $shop->choice);
        self::assertSame([], $policy->routes('person', 'blog'), 'turned off in that webspace');
        self::assertSame([], $policy->routes('unknown', null));
    }

    public function testItNamesEveryPathAndConditionItUses(): void
    {
        $policy = HandoffPolicy::fromArray([
            'routes' => [['when' => ['hours' => 'closed'], 'paths' => ['visitor_writes']]],
            'webspaces' => ['shop' => ['topics' => ['quote' => ['routes' => [['when' => ['locale' => 'es'], 'paths' => ['team_contact']]]]]]],
        ]);

        self::assertTrue($policy->hasRoutes());
        self::assertSame(['visitor_writes', 'team_contact'], $policy->pathNames());
        self::assertSame(['hours', 'locale'], $policy->conditionNames());
        self::assertFalse(HandoffPolicy::fromArray([])->hasRoutes());
    }

    public function testAWebspaceHasItsOwnHours(): void
    {
        $policy = HandoffPolicy::fromArray(['hours' => ['mon' => ['09:00-17:00']], 'webspaces' => ['shop' => ['hours' => ['sat' => ['10:00-12:00']]]]]);
        $monday = new \DateTimeImmutable('2026-11-16 10:00');

        self::assertTrue($policy->schedule(null)->isOpen($monday));
        self::assertFalse($policy->schedule('shop')->isOpen($monday));
        self::assertSame('W-', $policy->referencePrefix());
    }

    public function testAScheduleOpensInsideItsRanges(): void
    {
        $schedule = new HandoffSchedule(['mon' => ['09:00-13:00', '14:00-18:00']]);

        self::assertTrue($schedule->isOpen(new \DateTimeImmutable('2026-11-16 09:00')));
        self::assertFalse($schedule->isOpen(new \DateTimeImmutable('2026-11-16 13:30')));
        self::assertFalse($schedule->isOpen(new \DateTimeImmutable('2026-11-16 18:00')), 'the end is not included');
        self::assertFalse($schedule->isOpen(new \DateTimeImmutable('2026-11-17 10:00')));
        self::assertTrue((new HandoffSchedule())->isOpen(new \DateTimeImmutable('2026-11-17 03:00')), 'no hours means always open');
    }

    public function testAContactFillsWhatItLacksAndMasksThePhone(): void
    {
        $contact = (new HandoffContact(email: 'ana@example.com'))->orElse(new HandoffContact('other@example.com', '+54 11 5555 1234', 'Ana'));

        self::assertSame('ana@example.com', $contact->email);
        self::assertSame('Ana', $contact->name);
        self::assertSame('****1234', $contact->maskedPhone());
        self::assertTrue($contact->satisfies(ContactNeed::Phone));
        self::assertFalse((new HandoffContact())->satisfies(ContactNeed::Any));
    }
}
