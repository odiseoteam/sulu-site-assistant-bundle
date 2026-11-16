<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Capability;

use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationRefusedException;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Capability\HandoffCapability;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcome;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcomeStatus;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPathResult;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\HandoffDeskDouble;
use Odiseo\SuluSiteAssistantBundle\Tests\Double\Site;
use PHPUnit\Framework\TestCase;

final class HandoffCapabilityTest extends TestCase
{
    public function testTheTopicsAreTheModelsChoiceAndTheSiteTopicsAreThere(): void
    {
        [$request] = $this->capability()->tools();
        $properties = $request->inputSchema['properties'];
        self::assertIsArray($properties);
        self::assertIsArray($properties['topic']);

        self::assertSame(['person', 'quote', 'support', 'sensitive', 'unresolved'], $properties['topic']['enum']);
    }

    public function testARegisteredCaseIsShownByTheCardFromWhatTheDeskSaid(): void
    {
        $desk = new HandoffDeskDouble(new HandoffOutcome(HandoffOutcomeStatus::Registered, 'W-ABC234', 'visitor_writes', result: new HandoffPathResult('The visitor writes.')));
        $capability = $this->capability($desk);
        $state = new TurnState();

        $outcome = $capability->execute(HandoffCapability::REQUEST, ['topic' => 'quote', 'summary' => 'Wants a Sylius marketplace by March.', 'name' => 'Ana'], Site::context($state));

        self::assertFalse($outcome->refused());
        self::assertStringContainsString('W-ABC234', $outcome->resultText);
        self::assertSame('Ana', $desk->requests[0]->contact->name);
        self::assertSame('quote', $desk->requests[0]->topic->id);

        [$card] = $capability->components();
        self::assertNotNull($card->enrich);
        $payload = ($card->enrich)(['opening' => null], new EnrichmentContext(Site::context($state)));
        self::assertSame(['status' => 'registered', 'reference' => 'W-ABC234', 'path' => 'visitor_writes', 'open' => true], $payload);
    }

    public function testTheCardIsRefusedBeforeAnyCase(): void
    {
        [$card] = $this->capability()->components();
        self::assertNotNull($card->enrich);

        $this->expectException(PresentationRefusedException::class);
        ($card->enrich)(['opening' => ''], new EnrichmentContext(Site::context()));
    }

    public function testAnOfferTopicIsOfferedOnceBeforeItHandsOff(): void
    {
        $desk = new HandoffDeskDouble(new HandoffOutcome(HandoffOutcomeStatus::Registered, 'W-ABC234', 'visitor_writes'));
        $capability = $this->capability($desk);
        $state = new TurnState();
        $input = ['topic' => 'unresolved', 'summary' => 'Asked twice about hosting, nothing published.'];

        $first = $capability->execute(HandoffCapability::REQUEST, $input, Site::context($state));
        self::assertSame(HandoffCapability::OFFER_GATE, $first->blocked);
        self::assertSame([], $desk->requests, 'nothing was handed off yet');

        $second = $capability->execute(HandoffCapability::REQUEST, $input, Site::context($state));
        self::assertNull($second->blocked);
        self::assertCount(1, $desk->requests);
    }

    public function testAMissingContactHoldsTheCallAndSavesNoCard(): void
    {
        $desk = new HandoffDeskDouble(new HandoffOutcome(HandoffOutcomeStatus::NeedsContact, paths: ['visitor_writes' => 'Writes.'], need: ContactNeed::Any));
        $state = new TurnState();

        $outcome = $this->capability($desk)->execute(HandoffCapability::REQUEST, ['topic' => 'person', 'summary' => 'Wants a call.'], Site::context($state));

        self::assertSame(HandoffCapability::CONTACT_GATE, $outcome->blocked);
        self::assertStringContainsString('an email or a phone number', $outcome->resultText);
        self::assertNull($state->get('handoff_card'));
    }

    public function testAnInvalidContactIsSentBackToTheModel(): void
    {
        $context = Site::context();
        $capability = $this->capability();

        self::assertTrue($capability->execute(HandoffCapability::REQUEST, ['topic' => 'person', 'summary' => 'x', 'email' => 'not-an-email'], $context)->isError);
        self::assertTrue($capability->execute(HandoffCapability::REQUEST, ['topic' => 'person', 'summary' => 'x', 'phone' => '12'], $context)->isError);
        self::assertTrue($capability->execute(HandoffCapability::REQUEST, ['topic' => 'nope', 'summary' => 'x'], $context)->isError);
        self::assertTrue($capability->execute(HandoffCapability::REQUEST, ['topic' => 'person', 'summary' => ' '], $context)->isError);
    }

    public function testAFailedPathShowsTheCardAsFailed(): void
    {
        $desk = new HandoffDeskDouble(new HandoffOutcome(HandoffOutcomeStatus::Failed, 'W-ABC234', 'team_contact'));
        $state = new TurnState();
        $capability = $this->capability($desk);

        self::assertTrue($capability->execute(HandoffCapability::REQUEST, ['topic' => 'person', 'summary' => 'Call me.', 'email' => 'ana@example.com'], Site::context($state))->isError);

        [$card] = $capability->components();
        self::assertNotNull($card->enrich);
        self::assertSame('failed', ($card->enrich)(['opening' => null], new EnrichmentContext(Site::context($state)))['status']);
    }

    public function testThePromptForbidsQuotingAndPromisingAContact(): void
    {
        $text = \implode("\n", \array_map(static fn ($fragment): string => $fragment->text, $this->capability()->promptFragments()));

        self::assertStringContainsString('You do not quote a price', $text);
        self::assertStringContainsString('- quote: The visitor wants a price', $text);
    }

    private function capability(?HandoffDeskDouble $desk = null): HandoffCapability
    {
        return new HandoffCapability(
            $desk ?? new HandoffDeskDouble(new HandoffOutcome(HandoffOutcomeStatus::Unavailable)),
            HandoffPolicy::fromArray(['routes' => [['paths' => ['visitor_writes']]]]),
        );
    }
}
