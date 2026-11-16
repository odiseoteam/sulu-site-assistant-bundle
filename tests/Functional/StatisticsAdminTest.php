<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Entity\Handoff;

final class StatisticsAdminTest extends AdminTestCase
{
    public function testThePeriodsConversationsAndWhatCameOfThem(): void
    {
        foreach ([['s-1', 3, '/en/services'], ['s-2', 1, '/en/services'], ['s-3', 0, null]] as [$session, $turns, $page]) {
            $conversation = new Conversation();
            $conversation->setSessionId($session);
            $conversation->setPrincipalId('visitor-' . $session);
            $conversation->setState([]);
            $conversation->setPendingAppEvents([]);
            $conversation->setUpdatedAt(new \DateTimeImmutable());
            $conversation->setExpiresAt(new \DateTimeImmutable('+30 days'));
            $conversation->setWebspaceKey('example');
            $this->entityManager()->persist($conversation);
            $this->entityManager()->flush();
            $this->entityManager()->getConnection()->executeStatement(
                'UPDATE odiseo_site_assistant_conversation SET turn_count = ?, last_page = ? WHERE id = ?',
                [$turns, $page, $conversation->getId()],
            );
        }
        $handoff = new Handoff();
        $handoff->setReference('W-STAT22');
        $handoff->setTopic('quote');
        $handoff->setCategory('sale');
        $handoff->setSummary('x');
        $handoff->setWebspaceKey('example');
        $this->entityManager()->persist($handoff);
        $this->entityManager()->flush();

        $statistics = $this->json('GET', '/admin/api/site-assistant/statistics.json?period=7d&webspace=example');

        self::assertSame('7d', $statistics['period']['preset']);
        self::assertSame(2, $statistics['conversations'], 'a session without a message does not count');
        self::assertSame(1, $statistics['engaged']);
        self::assertSame(['quote' => 1], $statistics['handoffsByTopic']);
        self::assertSame([['page' => '/en/services', 'conversations' => 2]], $statistics['topPages']);
        self::assertSame([['key' => 'example', 'name' => 'Example']], $statistics['webspaces']);
    }
}
