<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Message;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Notice\HandoffNoticeInterface;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;

/** Runs every notice and records it. A case already notified is left alone, so a retry sends nothing twice. */
final class NotifyTeamHandler
{
    /** @param iterable<HandoffNoticeInterface> $notices */
    public function __construct(
        private readonly HandoffRepository $handoffs,
        private readonly iterable $notices,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(NotifyTeam $message): void
    {
        $handoff = $this->handoffs->find($message->handoffId);
        if (null === $handoff || null !== $handoff->getNotifiedAt()) {
            return;
        }

        foreach ($this->notices as $notice) {
            $notice->send($handoff);
        }

        $handoff->setNotifiedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
