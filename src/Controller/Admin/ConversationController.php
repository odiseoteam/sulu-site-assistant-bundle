<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Conversation\Transcript;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\MemoryFactInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntryInterface;
use Odiseo\SuluSiteAssistantBundle\Repository\ConversationRepository;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;
use Odiseo\SuluSiteAssistantBundle\Settings\ModelChoices;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The conversations: the list, one with its transcript, memory, cases and spend, and deleting
 * them with their messages. Read-only otherwise: integrations read the same endpoints.
 */
final class ConversationController implements SecuredControllerInterface
{
    /**
     * @param class-string<ConversationInterface> $conversationClass
     * @param class-string<MemoryFactInterface> $memoryFactClass
     * @param class-string<SpendEntryInterface> $spendEntryClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
        private readonly NormalizerInterface $normalizer,
        private readonly ConversationRepository $conversations,
        private readonly HandoffRepository $handoffs,
        private readonly Transcript $transcript,
        private readonly string $conversationClass,
        private readonly string $memoryFactClass,
        private readonly string $spendEntryClass,
    ) {
    }

    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(SiteAssistantAdmin::CONVERSATIONS_KEY) ?? [];

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create($this->conversationClass);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        // A session opened without a message has nothing to read.
        $listBuilder->where($fieldDescriptors['turnCount'], 0, ListBuilderInterface::WHERE_COMPARATOR_GREATER);
        foreach (['webspaceKey', 'locale'] as $filter) {
            $value = $request->query->get($filter);
            if (\is_string($value) && '' !== $value && isset($fieldDescriptors[$filter])) {
                $listBuilder->where($fieldDescriptors[$filter], $value);
            }
        }
        if (null === $request->query->get('sortBy')) {
            $listBuilder->sort($fieldDescriptors['createdAt'], 'desc');
        }

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            SiteAssistantAdmin::CONVERSATIONS_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize($representation->toArray(), 'json'));
    }

    public function getAction(int $id): Response
    {
        $conversation = $this->conversations->find($id);
        if (null === $conversation) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $conversation->getId(),
            'createdAt' => $conversation->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $conversation->getUpdatedAt()->format(\DATE_ATOM),
            'locale' => $conversation->getLocale(),
            'webspaceKey' => $conversation->getWebspaceKey(),
            'firstMessage' => $conversation->getFirstMessage(),
            'lastPage' => $conversation->getLastPage(),
            'turnCount' => $conversation->getTurnCount(),
            'contactOpenedAt' => $conversation->getContactOpenedAt()?->format(\DATE_ATOM),
            'spend' => $this->spend($conversation->getSessionId()),
            'models' => $this->models($conversation->getSessionId()),
            'memory' => $this->memory($conversation->getPrincipalId()),
            'handoffs' => \array_map(static fn (HandoffInterface $handoff): array => [
                'id' => $handoff->getId(),
                'reference' => $handoff->getReference(),
                'topic' => $handoff->getTopic(),
                'state' => $handoff->getState(),
                'path' => $handoff->getPath(),
                'createdAt' => $handoff->getCreatedAt()->format(\DATE_ATOM),
            ], $this->handoffs->findByConversation($conversation)),
            'transcript' => $this->transcript->entries($conversation),
        ]);
    }

    public function deleteAction(int $id): Response
    {
        $this->conversations->delete([$id]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function cdeleteAction(Request $request): Response
    {
        $ids = \array_values(\array_filter(\array_map('intval', \explode(',', (string) $request->query->get('ids', '')))));
        $this->conversations->delete($ids);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::CONVERSATIONS_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /** @return list<array{key: string, value: string, category: string, updatedAt: string}> */
    private function memory(string $subject): array
    {
        /** @var list<MemoryFactInterface> $facts */
        $facts = $this->em->getRepository($this->memoryFactClass)->findBy(['subject' => $subject], ['updatedAt' => 'DESC']);

        return \array_map(static fn (MemoryFactInterface $fact): array => [
            'key' => $fact->getFactKey(),
            'value' => $fact->getFactValue(),
            'category' => $fact->getCategory(),
            'updatedAt' => $fact->getUpdatedAt()->format(\DATE_ATOM),
        ], $facts);
    }

    /** @return array{usd: float, inputTokens: int, outputTokens: int, cacheReadTokens: int} */
    private function spend(string $sessionId): array
    {
        /** @var array{usd: ?string, inputTokens: ?string, outputTokens: ?string, cacheReadTokens: ?string} $row */
        $row = $this->em->createQueryBuilder()
            ->select('SUM(s.usd) AS usd', 'SUM(s.inputTokens) AS inputTokens', 'SUM(s.outputTokens) AS outputTokens', 'SUM(s.cacheReadTokens) AS cacheReadTokens')
            ->from($this->spendEntryClass, 's')
            ->where('s.sessionId = :session')
            ->setParameter('session', $sessionId)
            ->getQuery()
            ->getSingleResult();

        return [
            'usd' => \round((float) ($row['usd'] ?? 0), 4),
            'inputTokens' => (int) ($row['inputTokens'] ?? 0),
            'outputTokens' => (int) ($row['outputTokens'] ?? 0),
            'cacheReadTokens' => (int) ($row['cacheReadTokens'] ?? 0),
        ];
    }

    /** @return list<string> each model the conversation used, as "Claude Haiku 5.5 (Anthropic)" */
    private function models(string $sessionId): array
    {
        // Grouped with an aggregate, not DISTINCT: a list walker may append ORDER BY on the id.
        /** @var list<array{calls: int|string, model: string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('COUNT(s.id) AS calls', 's.model AS model')
            ->from($this->spendEntryClass, 's')
            ->where('s.sessionId = :session')
            ->andWhere('s.model IS NOT NULL')
            ->groupBy('s.model')
            ->setParameter('session', $sessionId)
            ->getQuery()
            ->getArrayResult();

        return \array_map(static fn (array $row): string => ModelChoices::describe($row['model']), $rows);
    }
}
