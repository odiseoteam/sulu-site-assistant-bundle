<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Event\HandoffEvent;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The cases handed to the team: the list, opening on the open ones, one case, and moving it
 * along (taken, resolved, reopened). Every move is a HandoffEvent.
 */
final class HandoffController implements SecuredControllerInterface
{
    /** The moves the team makes: action => [the states it starts from, the state it leaves]. */
    private const ACTIONS = [
        'take' => [[HandoffInterface::STATE_REQUESTED, HandoffInterface::STATE_FAILED], HandoffInterface::STATE_TAKEN],
        'resolve' => [[HandoffInterface::STATE_REQUESTED, HandoffInterface::STATE_TAKEN, HandoffInterface::STATE_FAILED], HandoffInterface::STATE_RESOLVED],
        'reopen' => [[HandoffInterface::STATE_RESOLVED], HandoffInterface::STATE_TAKEN],
    ];

    /** @param class-string<HandoffInterface> $handoffClass */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
        private readonly NormalizerInterface $normalizer,
        private readonly HandoffRepository $handoffs,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $handoffClass,
    ) {
    }

    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(SiteAssistantAdmin::HANDOFFS_KEY) ?? [];

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create($this->handoffClass);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        foreach (['state', 'topic', 'webspaceKey'] as $filter) {
            $value = $request->query->get($filter);
            if (\is_string($value) && '' !== $value && isset($fieldDescriptors[$filter])) {
                $listBuilder->in($fieldDescriptors[$filter], \explode(',', $value));
            }
        }
        if (null === $request->query->get('sortBy')) {
            $listBuilder->sort($fieldDescriptors['createdAt'], 'desc');
        }

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            SiteAssistantAdmin::HANDOFFS_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize($representation->toArray(), 'json'));
    }

    public function getAction(int $id): Response
    {
        $handoff = $this->handoffs->find($id);

        return null === $handoff ? new JsonResponse(null, Response::HTTP_NOT_FOUND) : new JsonResponse($this->serialize($handoff));
    }

    public function putAction(int $id, Request $request): Response
    {
        $handoff = $this->handoffs->find($id);
        if (null === $handoff) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $action = (string) $request->query->get('action', '');
        [$from, $to] = self::ACTIONS[$action] ?? [null, null];
        if (null === $from || null === $to) {
            return new JsonResponse(['code' => 'unknown_action', 'message' => \sprintf('Unknown action "%s". Use one of: %s.', $action, \implode(', ', \array_keys(self::ACTIONS)))], Response::HTTP_BAD_REQUEST);
        }
        if (!\in_array($handoff->getState(), $from, true)) {
            return new JsonResponse(['code' => 'invalid_transition', 'message' => \sprintf('A case in state "%s" cannot be moved with "%s".', $handoff->getState(), $action)], Response::HTTP_CONFLICT);
        }

        $handoff->setState($to);
        $handoff->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->dispatcher->dispatch(new HandoffEvent($handoff), HandoffEvent::STATE_CHANGED);

        return new JsonResponse($this->serialize($handoff));
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::HANDOFFS_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    private function serialize(HandoffInterface $handoff): array
    {
        $actions = [];
        foreach (self::ACTIONS as $action => [$from]) {
            if (\in_array($handoff->getState(), $from, true)) {
                $actions[] = $action;
            }
        }

        return [
            'id' => $handoff->getId(),
            'reference' => $handoff->getReference(),
            'topic' => $handoff->getTopic(),
            'category' => $handoff->getCategory(),
            'state' => $handoff->getState(),
            'path' => $handoff->getPath(),
            'summary' => $handoff->getSummary(),
            'contactName' => $handoff->getContactName(),
            'contactEmail' => $handoff->getContactEmail(),
            'contactPhone' => $handoff->getContactPhone(),
            'page' => $handoff->getPage(),
            'webspaceKey' => $handoff->getWebspaceKey(),
            'locale' => $handoff->getLocale(),
            'conversationId' => $handoff->getConversation()?->getId(),
            'notifiedAt' => $handoff->getNotifiedAt()?->format(\DATE_ATOM),
            'createdAt' => $handoff->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $handoff->getUpdatedAt()?->format(\DATE_ATOM),
            'actions' => $actions,
        ];
    }
}
