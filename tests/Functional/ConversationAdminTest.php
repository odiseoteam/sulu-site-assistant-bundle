<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Entity\Handoff;
use Odiseo\SuluSiteAssistantBundle\Entity\Message;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntry;

final class ConversationAdminTest extends AdminTestCase
{
    public function testTheListShowsConversationsWithAMessageNewestFirst(): void
    {
        $this->conversation('s-old', 'Older question', new \DateTimeImmutable('-2 days'));
        $this->conversation('s-new', 'Newer question', new \DateTimeImmutable('-1 hour'));
        $this->conversation('s-empty', null, new \DateTimeImmutable(), turns: 0);

        $list = $this->json('GET', '/admin/api/site-assistant/conversations.json?fields=id,createdAt,firstMessage,turnCount');

        $rows = $list['_embedded']['site_assistant_conversations'] ?? [];
        self::assertIsArray($rows);
        self::assertSame(['Newer question', 'Older question'], \array_column($rows, 'firstMessage'), 'a session without a message is not listed');
    }

    public function testOneConversationComesWithItsTranscriptCasesSpendAndModels(): void
    {
        $conversation = $this->conversation('s-1', 'Do you build marketplaces?', new \DateTimeImmutable());
        $this->message($conversation, 0, 'user', 'Do you build marketplaces?', ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Do you build marketplaces?']]]);
        $this->message($conversation, 1, 'assistant', null, ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'tu-1', 'name' => 'search_content', 'input' => ['query' => 'marketplace']]]]);
        $this->message($conversation, 2, 'assistant', 'We do.', ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'We do.']]]);

        $handoff = new Handoff();
        $handoff->setConversation($conversation);
        $handoff->setReference('W-ABC234');
        $handoff->setTopic('quote');
        $handoff->setCategory('sale');
        $handoff->setSummary('Wants a marketplace.');
        $this->entityManager()->persist($handoff);

        $spend = new SpendEntry();
        $spend->setSessionId('s-1');
        $spend->setSpentOn(new \DateTimeImmutable('today'));
        $spend->setUsd(0.0123);
        $spend->setModel('claude-haiku-5-5');
        $spend->setTokens(1000, 200, 0, 500);
        $this->entityManager()->persist($spend);
        $this->entityManager()->flush();

        $detail = $this->json('GET', '/admin/api/site-assistant/conversations/' . $conversation->getId() . '.json');

        self::assertSame('Do you build marketplaces?', $detail['firstMessage']);
        self::assertSame(['Claude Haiku 5.5 (Anthropic)'], $detail['models']);
        self::assertSame(0.0123, $detail['spend']['usd'] ?? null);
        self::assertSame('W-ABC234', $detail['handoffs'][0]['reference'] ?? null);
        self::assertIsArray($detail['transcript']);
        self::assertCount(3, $detail['transcript']);
        self::assertSame('marketplace', $detail['transcript'][1]['tools'][0]['query'] ?? null);
    }

    public function testDeletingAConversationKeepsItsCases(): void
    {
        $conversation = $this->conversation('s-1', 'Hi', new \DateTimeImmutable());
        $handoff = new Handoff();
        $handoff->setConversation($conversation);
        $handoff->setReference('W-KEEP22');
        $handoff->setTopic('person');
        $handoff->setCategory('visitor_request');
        $handoff->setSummary('Wants a call.');
        $this->entityManager()->persist($handoff);
        $this->entityManager()->flush();
        $id = $conversation->getId();

        $this->json('DELETE', '/admin/api/site-assistant/conversations.json?ids=' . $id, status: 204);

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(Conversation::class, $id));
        self::assertNotNull($this->entityManager()->getRepository(Handoff::class)->findOneBy(['reference' => 'W-KEEP22']));
    }

    public function testAnUnknownConversationIsNotFound(): void
    {
        $this->json('GET', '/admin/api/site-assistant/conversations/999999.json', status: 404);
    }

    private function conversation(string $sessionId, ?string $first, \DateTimeImmutable $createdAt, int $turns = 1): Conversation
    {
        $conversation = new Conversation();
        $conversation->setSessionId($sessionId);
        $conversation->setPrincipalId('visitor-' . $sessionId);
        $conversation->setState([]);
        $conversation->setPendingAppEvents([]);
        $conversation->setUpdatedAt($createdAt);
        $conversation->setExpiresAt($createdAt->modify('+30 days'));
        $conversation->setWebspaceKey('example');
        $conversation->setLocale('en');
        $this->entityManager()->persist($conversation);
        $this->entityManager()->flush();

        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE odiseo_site_assistant_conversation SET turn_count = ?, first_message = ?, created_at = ? WHERE id = ?',
            [$turns, $first, $createdAt->format('Y-m-d H:i:s'), $conversation->getId()],
        );
        $this->entityManager()->refresh($conversation);

        return $conversation;
    }

    /** @param array<string, mixed> $payload */
    private function message(Conversation $conversation, int $position, string $role, ?string $text, array $payload): void
    {
        $message = new Message();
        $message->setConversation($conversation);
        $message->setPosition($position);
        $message->setRole($role);
        $message->setText($text);
        $message->setPayload($payload);
        $this->entityManager()->persist($message);
        $this->entityManager()->flush();
    }
}
