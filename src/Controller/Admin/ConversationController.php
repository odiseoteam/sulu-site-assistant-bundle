<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\MemoryFactInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\MessageInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelper;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/** The admin's read of the conversations: the list, one transcript, and deleting on request. */
final class ConversationController implements SecuredControllerInterface
{
    /** Tools whose calls the transcript shows: what the assistant looked up and presented. */
    private const VISIBLE_TOOLS = ['search_content', 'get_content', 'present_pages', 'present_comparison', 'present_handoff'];

    /**
     * @param class-string<ConversationInterface> $conversationClass
     * @param class-string<MessageInterface> $messageClass
     * @param class-string<MemoryFactInterface> $memoryFactClass
     * @param class-string<SpendEntryInterface> $spendEntryClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelper $restHelper,
        private readonly NormalizerInterface $normalizer,
        private readonly string $conversationClass,
        private readonly string $messageClass,
        private readonly string $memoryFactClass,
        private readonly string $spendEntryClass,
    ) {
    }

    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(SiteAssistantAdmin::RESOURCE_KEY) ?? [];

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create($this->conversationClass);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        // A session opened without a message has nothing to read.
        $listBuilder->where($fieldDescriptors['turnCount'], 0, ListBuilderInterface::WHERE_COMPARATOR_GREATER);
        if (null === $request->query->get('sortBy')) {
            $listBuilder->sort($fieldDescriptors['createdAt'], 'desc');
        }

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            SiteAssistantAdmin::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize($representation->toArray(), 'json'));
    }

    public function getAction(int $id): Response
    {
        $conversation = $this->em->find($this->conversationClass, $id);
        if (!$conversation instanceof ConversationInterface) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $conversation->getId(),
            'createdAt' => $conversation->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $conversation->getUpdatedAt()->format(\DATE_ATOM),
            'locale' => $conversation->getLocale(),
            'webspace' => $conversation->getWebspaceKey(),
            'firstMessage' => $conversation->getFirstMessage(),
            'lastPage' => $conversation->getLastPage(),
            'turnCount' => $conversation->getTurnCount(),
            'handoffShownAt' => $conversation->getHandoffShownAt()?->format(\DATE_ATOM),
            'handoffClickedAt' => $conversation->getHandoffClickedAt()?->format(\DATE_ATOM),
            'spend' => $this->spend($conversation->getSessionId()),
            'memory' => $this->memory($conversation->getPrincipalId()),
            'transcript' => $this->transcript($conversation),
        ]);
    }

    public function deleteAction(int $id): Response
    {
        $conversation = $this->em->find($this->conversationClass, $id);
        if ($conversation instanceof ConversationInterface) {
            $this->em->remove($conversation);
            $this->em->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function cdeleteAction(Request $request): Response
    {
        $ids = \array_filter(\array_map('intval', \explode(',', (string) $request->query->get('ids', ''))));
        foreach ($ids as $id) {
            $conversation = $this->em->find($this->conversationClass, $id);
            if ($conversation instanceof ConversationInterface) {
                $this->em->remove($conversation);
            }
        }
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /** @return list<array{role: string, text: ?string, tools: list<array{name: string, input: mixed}>, createdAt: string}> */
    private function transcript(ConversationInterface $conversation): array
    {
        /** @var list<MessageInterface> $messages */
        $messages = $this->em->getRepository($this->messageClass)->findBy(['conversation' => $conversation], ['position' => 'ASC']);

        $entries = [];
        foreach ($messages as $message) {
            $tools = self::tools($message->getPayload());
            if (null === $message->getText() && [] === $tools) {
                continue;
            }
            $entries[] = [
                'role' => $message->getRole(),
                'text' => $message->getText(),
                'tools' => $tools,
                'createdAt' => $message->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return $entries;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<array{name: string, input: mixed}>
     */
    private static function tools(array $payload): array
    {
        $tools = [];
        foreach (\is_array($payload['content'] ?? null) ? $payload['content'] : [] as $block) {
            $name = \is_array($block) && 'tool_use' === ($block['type'] ?? null) ? ($block['name'] ?? null) : null;
            if (\is_string($name) && \in_array($name, self::VISIBLE_TOOLS, true)) {
                $tools[] = ['name' => $name, 'input' => $block['input'] ?? null];
            }
        }

        return $tools;
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

    /** @return array{usd: float, inputTokens: int, outputTokens: int} */
    private function spend(string $sessionId): array
    {
        /** @var array{usd: ?string, inputTokens: ?string, outputTokens: ?string} $row */
        $row = $this->em->createQueryBuilder()
            ->select('SUM(s.usd) AS usd', 'SUM(s.inputTokens) AS inputTokens', 'SUM(s.outputTokens) AS outputTokens')
            ->from($this->spendEntryClass, 's')
            ->where('s.sessionId = :session')
            ->setParameter('session', $sessionId)
            ->getQuery()
            ->getSingleResult();

        return [
            'usd' => \round((float) ($row['usd'] ?? 0), 4),
            'inputTokens' => (int) ($row['inputTokens'] ?? 0),
            'outputTokens' => (int) ($row['outputTokens'] ?? 0),
        ];
    }
}
