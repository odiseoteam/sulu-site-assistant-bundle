<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;

final class HandoffTest extends IntegrationTestCase
{
    public function testWithoutAContactTheVisitorChoosesHowToGoOn(): void
    {
        $this->script(
            FakeProvider::toolCall('request_handoff', ['topic' => 'quote', 'summary' => 'Wants a B2B store on Sylius by March.'], 'tu-1'),
            FakeProvider::toolCall('present_handoff', ['opening' => ''], 'tu-2'),
        );

        $sessionId = $this->startSession();
        $events = $this->chat($sessionId, 'How much would a B2B store cost?');

        $card = self::cards($events)['handoff'] ?? null;
        self::assertIsArray($card);
        self::assertSame('choose_path', $card['status']);
        self::assertSame(['visitor_writes', 'team_contact'], $card['paths']);
        self::assertIsString($card['reference']);
        self::assertStringStartsWith('W-', $card['reference']);

        $handoff = $this->handoff($card['reference']);
        self::assertNull($handoff->getPath(), 'saved before a path is chosen');
        self::assertSame('quote', $handoff->getTopic());
        self::assertSame('sale', $handoff->getCategory());
        self::assertSame('example', $handoff->getWebspaceKey());
        self::assertSame('en', $handoff->getLocale());
        self::assertSame('/en/sylius-development', $handoff->getPage());
        self::assertSame($sessionId, $handoff->getConversation()?->getSessionId());
    }

    public function testALeftEmailHasTheTeamWriteAndNotifiesThem(): void
    {
        $this->script(
            FakeProvider::toolCall('request_handoff', ['topic' => 'person', 'summary' => 'Wants a call about a migration.', 'email' => 'ana@example.com', 'name' => 'Ana'], 'tu-1'),
            FakeProvider::toolCall('present_handoff', ['opening' => ''], 'tu-2'),
        );

        $events = $this->chat($this->startSession(), 'Please have someone email me at ana@example.com, I am Ana');

        $card = self::cards($events)['handoff'] ?? null;
        self::assertIsArray($card);
        self::assertSame('registered', $card['status']);
        self::assertSame('team_contact', $card['path']);
        self::assertSame(['visitor_writes'], $card['self_serve']);

        $handoff = $this->handoff((string) $card['reference']);
        self::assertSame('Ana', $handoff->getContactName());
        self::assertSame('ana@example.com', $handoff->getContactEmail());
        self::assertNotNull($handoff->getNotifiedAt(), 'the notice ran with the turn');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'team@example.com');
        self::assertEmailAddressContains($email, 'Reply-To', 'ana@example.com');
        self::assertEmailHtmlBodyContains($email, (string) $card['reference']);
        self::assertEmailHtmlBodyContains($email, 'https://example.com/admin/#/site-assistant/handoffs/' . $handoff->getId());
    }

    public function testAChosenPathRegistersTheSameCase(): void
    {
        $this->script(
            FakeProvider::toolCall('request_handoff', ['topic' => 'support', 'summary' => 'The checkout we built for them fails.'], 'tu-1'),
            FakeProvider::toolCall('present_handoff', ['opening' => 'Sorry about that.'], 'tu-2'),
            FakeProvider::toolCall('request_handoff', ['topic' => 'support', 'summary' => 'The checkout we built for them fails.', 'path' => 'visitor_writes'], 'tu-3'),
            FakeProvider::toolCall('present_handoff', ['opening' => ''], 'tu-4'),
        );

        $sessionId = $this->startSession();
        $first = self::cards($this->chat($sessionId, 'Our checkout is broken'))['handoff'] ?? [];
        $second = self::cards($this->chat($sessionId, 'I will write to you'))['handoff'] ?? [];

        self::assertSame('Sorry about that.', $first['opening'] ?? null);
        self::assertSame($first['reference'] ?? null, $second['reference'] ?? null, 'one case, not two');
        self::assertSame('visitor_writes', $second['path'] ?? null);
        self::assertSame('visitor_writes', $this->handoff((string) ($second['reference'] ?? ''))->getPath());
        self::assertEmailCount(0);
    }

    public function testAnOfferTopicIsOfferedBeforeAnythingIsSaved(): void
    {
        $this->script(
            FakeProvider::toolCall('request_handoff', ['topic' => 'unresolved', 'summary' => 'Nothing published about hosting.'], 'tu-1'),
            FakeProvider::text('I could not find that. Want me to pass it to the team?'),
        );

        $events = $this->chat($this->startSession(), 'Do you host the stores?');

        self::assertArrayNotHasKey('handoff', self::cards($events));
        self::assertSame(0, $this->entityManager()->getRepository(HandoffInterface::class)->count([]));
    }

    private function handoff(string $reference): HandoffInterface
    {
        $this->entityManager()->clear();
        $handoff = $this->entityManager()->getRepository(HandoffInterface::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(HandoffInterface::class, $handoff);

        return $handoff;
    }
}
