<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;

class ConversationRepository
{
    /** @param class-string<ConversationInterface> $class */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $class,
    ) {
    }

    public function find(int $id): ?ConversationInterface
    {
        /** @var ConversationInterface|null */
        return $this->em->find($this->class, $id);
    }

    public function findOneBySessionId(string $sessionId): ?ConversationInterface
    {
        /** @var ConversationInterface|null */
        return $this->em->getRepository($this->class)->findOneBy(['sessionId' => $sessionId]);
    }

    /**
     * Deletes the conversations with their messages; their cases stay, without them.
     *
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        $deleted = 0;
        foreach ($this->em->getRepository($this->class)->findBy(['id' => $ids]) as $conversation) {
            $this->em->remove($conversation);
            ++$deleted;
        }
        $this->em->flush();

        return $deleted;
    }
}
