<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;

class HandoffRepository
{
    /** @param class-string<HandoffInterface> $class */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $class,
    ) {
    }

    public function create(): HandoffInterface
    {
        return new $this->class();
    }

    public function find(int $id): ?HandoffInterface
    {
        /** @var HandoffInterface|null */
        return $this->em->find($this->class, $id);
    }

    public function findOneByReference(string $reference): ?HandoffInterface
    {
        /** @var HandoffInterface|null */
        return $this->em->getRepository($this->class)->findOneBy(['reference' => $reference]);
    }

    /** The open case of this conversation on this topic, the latest first. */
    public function findOpen(ConversationInterface $conversation, string $topic): ?HandoffInterface
    {
        /** @var HandoffInterface|null */
        return $this->em->getRepository($this->class)->findOneBy(
            ['conversation' => $conversation, 'topic' => $topic, 'state' => HandoffInterface::OPEN_STATES],
            ['id' => 'DESC'],
        );
    }

    /** @return list<HandoffInterface> the conversation's cases, the latest first */
    public function findByConversation(ConversationInterface $conversation): array
    {
        /** @var list<HandoffInterface> */
        return $this->em->getRepository($this->class)->findBy(['conversation' => $conversation], ['id' => 'DESC']);
    }

    public function countOpen(): int
    {
        return $this->em->getRepository($this->class)->count(['state' => HandoffInterface::OPEN_STATES]);
    }
}
