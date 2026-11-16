<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

use Odiseo\SuluSiteAssistantBundle\Entity\Handoff;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Event\HandoffEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class HandoffAdminTest extends AdminTestCase
{
    public function testTheListFiltersByState(): void
    {
        $this->handoff('W-OPEN22', HandoffInterface::STATE_REQUESTED);
        $this->handoff('W-DONE22', HandoffInterface::STATE_RESOLVED);

        $all = $this->json('GET', '/admin/api/site-assistant/handoffs.json?fields=id,reference,state');
        $open = $this->json('GET', '/admin/api/site-assistant/handoffs.json?fields=id,reference,state&state=requested,taken');

        self::assertCount(2, $all['_embedded']['site_assistant_handoffs'] ?? []);
        self::assertSame(['W-OPEN22'], \array_column($open['_embedded']['site_assistant_handoffs'] ?? [], 'reference'));
    }

    public function testACaseIsTakenResolvedAndReopenedWithAnEventEachTime(): void
    {
        $id = $this->handoff('W-MOVE22', HandoffInterface::STATE_REQUESTED)->getId();
        $moves = [];
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(HandoffEvent::STATE_CHANGED, static function (HandoffEvent $event) use (&$moves): void {
            $moves[] = $event->handoff->getState();
        });

        $case = $this->json('GET', '/admin/api/site-assistant/handoffs/' . $id . '.json');
        self::assertSame(['take', 'resolve'], $case['actions']);

        self::assertSame('taken', $this->json('PUT', '/admin/api/site-assistant/handoffs/' . $id . '.json?action=take')['state']);
        self::assertSame('resolved', $this->json('PUT', '/admin/api/site-assistant/handoffs/' . $id . '.json?action=resolve')['state']);
        self::assertSame('taken', $this->json('PUT', '/admin/api/site-assistant/handoffs/' . $id . '.json?action=reopen')['state']);
        self::assertSame(['taken', 'resolved', 'taken'], $moves);
    }

    public function testAMoveThatDoesNotFitTheStateIsRefused(): void
    {
        $id = $this->handoff('W-DONE33', HandoffInterface::STATE_RESOLVED)->getId();

        $this->json('PUT', '/admin/api/site-assistant/handoffs/' . $id . '.json?action=take', status: 409);
        $this->json('PUT', '/admin/api/site-assistant/handoffs/' . $id . '.json?action=archive', status: 400);
        $this->json('GET', '/admin/api/site-assistant/handoffs/999999.json', status: 404);
    }

    private function handoff(string $reference, string $state): HandoffInterface
    {
        $handoff = new Handoff();
        $handoff->setReference($reference);
        $handoff->setTopic('quote');
        $handoff->setCategory('sale');
        $handoff->setSummary('Wants a store.');
        $handoff->setState($state);
        $handoff->setContactEmail('ana@example.com');
        $this->entityManager()->persist($handoff);
        $this->entityManager()->flush();

        return $handoff;
    }
}
