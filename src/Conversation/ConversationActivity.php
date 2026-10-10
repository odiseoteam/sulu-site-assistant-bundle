<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Conversation;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Psr\Log\LoggerInterface;

/**
 * The columns the admin lists and counts. Bulk updates, so the core's optimistic lock on the
 * conversation is untouched while a turn runs; a failure here never reaches the visitor.
 */
final class ConversationActivity
{
    private const MESSAGE_CHARS = 255;
    private const PAGE_CHARS = 255;

    /** @param class-string<ConversationInterface> $conversationClass */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $conversationClass,
    ) {
    }

    public function turnStarted(string $sessionId, string $message, ?string $page): void
    {
        $this->update($sessionId, 'turn', function () use ($sessionId, $message, $page): void {
            $query = $this->em->createQueryBuilder()
                ->update($this->conversationClass, 'c')
                ->set('c.turnCount', 'c.turnCount + 1')
                ->set('c.firstMessage', 'COALESCE(c.firstMessage, :message)')
                ->where('c.sessionId = :session')
                ->setParameter('message', '' === \trim($message) ? null : \mb_substr(\trim($message), 0, self::MESSAGE_CHARS))
                ->setParameter('session', $sessionId);

            if (null !== $page && '' !== $page) {
                $query->set('c.lastPage', ':page')->setParameter('page', \mb_substr($page, 0, self::PAGE_CHARS));
            }

            $query->getQuery()->execute();
        });
    }

    public function handoffShown(string $sessionId): void
    {
        $this->stamp($sessionId, 'handoffShownAt');
    }

    public function handoffClicked(string $sessionId): void
    {
        $this->stamp($sessionId, 'handoffClickedAt');
    }

    /** Sets the timestamp the first time only. */
    private function stamp(string $sessionId, string $field): void
    {
        $this->update($sessionId, $field, function () use ($sessionId, $field): void {
            $this->em->createQueryBuilder()
                ->update($this->conversationClass, 'c')
                ->set('c.' . $field, ':now')
                ->where('c.sessionId = :session')
                ->andWhere(\sprintf('c.%s IS NULL', $field))
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('session', $sessionId)
                ->getQuery()
                ->execute();
        });
    }

    private function update(string $sessionId, string $what, \Closure $update): void
    {
        try {
            $update();
        } catch (\Throwable $failed) {
            $this->logger->error('the conversation activity could not be recorded', [
                'what' => $what,
                'session' => \substr(\hash('sha256', $sessionId), 0, 12),
                'exception' => $failed,
            ]);
        }
    }
}
