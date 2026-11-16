<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Session\SessionContext;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Event\HandoffEvent;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\ContactPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcome;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcomeStatus;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPathResult;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffRequest;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffRoute;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSituation;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffTopic;
use Odiseo\SuluSiteAssistantBundle\Model\PathChoice;
use Odiseo\SuluSiteAssistantBundle\Repository\ConversationRepository;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Finds the route, checks the contact, saves the handoff and opens its path. The handoff is saved
 * before its path runs, so a failing path or a visitor who leaves does not lose the case.
 */
final class HandoffDesk implements HandoffDeskInterface
{
    private const MAX_SUMMARY_CHARS = 4000;

    private const MAX_PAGE_CHARS = 255;

    /** No ambiguous glyphs: a person reads it out or types it. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const REFERENCE_LENGTH = 6;

    /** @var array<string, HandoffPathInterface> */
    private readonly array $paths;

    /** @var array<string, RouteConditionInterface> */
    private readonly array $conditions;

    /**
     * @param iterable<string, HandoffPathInterface> $paths by name
     * @param iterable<string, RouteConditionInterface> $conditions by name
     */
    public function __construct(
        private readonly HandoffPolicy $policy,
        iterable $paths,
        iterable $conditions,
        private readonly HandoffRepository $handoffs,
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $entityManager,
        private readonly VisitorContext $visitor,
        private readonly HandoffContactResolverInterface $contacts,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
        $this->paths = \is_array($paths) ? $paths : \iterator_to_array($paths);
        $this->conditions = \is_array($conditions) ? $conditions : \iterator_to_array($conditions);
    }

    public function open(HandoffRequest $request, SessionContext $session): HandoffOutcome
    {
        $conversation = $this->conversations->findOneBySessionId($session->sessionId);
        $webspace = $conversation?->getWebspaceKey() ?? $this->visitor->webspace();
        $existing = null === $conversation ? null : $this->handoffs->findOpen($conversation, $request->topic->id);

        $contact = $request->contact
            ->orElse(new HandoffContact($existing?->getContactEmail(), $existing?->getContactPhone(), $existing?->getContactName()))
            ->orElse($this->contacts->known($session));

        $situation = $this->situation($webspace, $session, $contact);
        $open = $situation->schedule->isOpen($situation->now);

        $route = $this->route($request->topic->id, $webspace, $situation);
        $wanted = null === $route ? null : $this->pathForWrittenContact($route, $request->contact);

        // A path already opened stays, whatever the route says now, unless a written contact asks for another.
        if (null === $request->path && null !== $existing?->getPath() && \in_array($wanted, [null, $existing->getPath()], true)) {
            $handoff = $this->save($existing, $request, $session, $conversation, $webspace, $contact);

            return $this->registered($handoff, (string) $handoff->getPath(), $route, $contact, $open);
        }

        if (null === $route) {
            return new HandoffOutcome(HandoffOutcomeStatus::Unavailable, open: $open);
        }

        $path = $request->path ?? $this->pick($route, $contact, $request->contact);
        if (null !== $path && !\in_array($path, $route->paths, true)) {
            return new HandoffOutcome(HandoffOutcomeStatus::ChoosePath, $existing?->getReference(), paths: $this->described($route->paths), open: $open);
        }

        if (null !== $path) {
            $need = $this->need($path, $route);
            if (ContactPolicy::Required === $this->contactPolicy($route) && !$contact->satisfies($need)) {
                return new HandoffOutcome(HandoffOutcomeStatus::NeedsContact, $existing?->getReference(), paths: $this->described($this->pathsThatNeedNothing($route)), need: $need, open: $open);
            }
        }

        $handoff = $this->save($existing, $request, $session, $conversation, $webspace, $contact);
        if (null === $path) {
            return new HandoffOutcome(HandoffOutcomeStatus::ChoosePath, $handoff->getReference(), paths: $this->described($route->paths), open: $open);
        }

        if ($path === $handoff->getPath()) {
            return $this->registered($handoff, $path, $route, $contact, $open);
        }

        return $this->openPath($handoff, $path, $route, $contact, $open);
    }

    public function paths(HandoffTopic $topic, SessionContext $session): array
    {
        $conversation = $this->conversations->findOneBySessionId($session->sessionId);
        $webspace = $conversation?->getWebspaceKey() ?? $this->visitor->webspace();
        $route = $this->route($topic->id, $webspace, $this->situation($webspace, $session, $this->contacts->known($session)));

        return null === $route ? [] : $this->described($route->paths);
    }

    private function situation(?string $webspace, SessionContext $session, HandoffContact $contact): HandoffSituation
    {
        return new HandoffSituation(
            $session->localNow() ?? new \DateTimeImmutable(),
            $this->policy->schedule($webspace),
            $contact,
            $this->visitor->locale(),
        );
    }

    private function openPath(HandoffInterface $handoff, string $path, HandoffRoute $route, HandoffContact $contact, bool $open): HandoffOutcome
    {
        try {
            $result = $this->paths[$path]->open($handoff);
        } catch (\Throwable $error) {
            $this->logger->error('Handoff path failed', ['reference' => $handoff->getReference(), 'path' => $path, 'exception' => $error]);
            $handoff->setState(HandoffInterface::STATE_FAILED);
            $handoff->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->dispatcher->dispatch(new HandoffEvent($handoff), HandoffEvent::FAILED);

            return new HandoffOutcome(HandoffOutcomeStatus::Failed, $handoff->getReference(), $path, open: $open);
        }

        $handoff->setPath($path);
        $handoff->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new HandoffEvent($handoff), HandoffEvent::PATH_OPENED);

        return $this->registered($handoff, $path, $route, $contact, $open, $result);
    }

    private function registered(HandoffInterface $handoff, string $path, ?HandoffRoute $route, HandoffContact $contact, bool $open, ?HandoffPathResult $result = null): HandoffOutcome
    {
        $others = \array_values(\array_diff($route->paths ?? [], [$path]));

        return new HandoffOutcome(
            HandoffOutcomeStatus::Registered,
            $handoff->getReference(),
            $path,
            result: $result,
            maskedPhone: $contact->maskedPhone(),
            open: $open,
            alternatives: null === $route ? [] : $this->alternatives($route, $others, $contact),
            selfServe: null === $route ? [] : \array_values(\array_filter($others, fn (string $other): bool => ContactNeed::None === $this->need($other, $route))),
        );
    }

    private function save(
        ?HandoffInterface $existing,
        HandoffRequest $request,
        SessionContext $session,
        ?ConversationInterface $conversation,
        ?string $webspace,
        HandoffContact $contact,
    ): HandoffInterface {
        $handoff = $existing ?? $this->handoffs->create();
        $handoff->setConversation($conversation);
        $handoff->setWebspaceKey($webspace);
        $handoff->setTopic($request->topic->id);
        $handoff->setCategory($request->topic->category->value);
        $handoff->setSummary(\mb_substr($request->summary, 0, self::MAX_SUMMARY_CHARS));
        $handoff->setContactName($contact->name);
        $handoff->setContactEmail($contact->email);
        $handoff->setContactPhone($contact->phone);

        if (null === $existing) {
            $handoff->setReference($this->reference());
            $handoff->setPage(self::page($session));
            $handoff->setLocale($conversation?->getLocale() ?? $this->visitor->locale());
            $this->entityManager->persist($handoff);
        } else {
            $handoff->setUpdatedAt(new \DateTimeImmutable());
        }
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new HandoffEvent($handoff), null === $existing ? HandoffEvent::OPENED : HandoffEvent::UPDATED);

        return $handoff;
    }

    /** Random, so it does not tell how many cases the site gets. */
    private function reference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::REFERENCE_LENGTH; ++$i) {
                $code .= self::REFERENCE_ALPHABET[\random_int(0, \strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = $this->policy->referencePrefix() . $code;
        } while (null !== $this->handoffs->findOneByReference($reference));

        return $reference;
    }

    private function route(string $topic, ?string $webspace, HandoffSituation $situation): ?HandoffRoute
    {
        foreach ($this->policy->routes($topic, $webspace) as $route) {
            foreach ($route->when as $name => $expected) {
                if (!$this->conditions[$name]->matches($expected, $situation)) {
                    continue 2;
                }
            }

            return $route;
        }

        return null;
    }

    /**
     * The single path, the one a written contact asks for, or the first in priority order; null
     * leaves the choice to the visitor.
     */
    private function pick(HandoffRoute $route, HandoffContact $contact, HandoffContact $written): ?string
    {
        if (1 === \count($route->paths)) {
            return $route->paths[0];
        }

        $wanted = $this->pathForWrittenContact($route, $written);
        if (null !== $wanted || PathChoice::Visitor === $route->choice) {
            return $wanted;
        }

        foreach ($route->paths as $path) {
            if ($contact->satisfies($this->need($path, $route))) {
                return $path;
            }
        }

        return $route->paths[0] ?? null;
    }

    /** Leaving a contact already answers how to go on. */
    private function pathForWrittenContact(HandoffRoute $route, HandoffContact $written): ?string
    {
        foreach ($route->paths as $path) {
            $need = $this->need($path, $route);
            if (ContactNeed::None !== $need && $written->satisfies($need)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param list<string> $others
     *
     * @return array<string, bool>
     */
    private function alternatives(HandoffRoute $route, array $others, HandoffContact $contact): array
    {
        $alternatives = [];
        foreach ($others as $other) {
            $need = $this->need($other, $route);
            if (ContactNeed::None !== $need) {
                $alternatives[$other] = $contact->satisfies($need);
            }
        }

        return $alternatives;
    }

    private function need(string $path, HandoffRoute $route): ContactNeed
    {
        return ContactPolicy::Off === $route->contact ? ContactNeed::None : $this->paths[$path]->contactNeed();
    }

    private function contactPolicy(HandoffRoute $route): ContactPolicy
    {
        return $route->contact ?? ContactPolicy::Required;
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, string>
     */
    private function described(array $paths): array
    {
        $described = [];
        foreach ($paths as $path) {
            $described[$path] = $this->paths[$path]->description();
        }

        return $described;
    }

    /** @return list<string> */
    private function pathsThatNeedNothing(HandoffRoute $route): array
    {
        return \array_values(\array_filter($route->paths, fn (string $path): bool => ContactNeed::None === $this->paths[$path]->contactNeed()));
    }

    private static function page(SessionContext $session): ?string
    {
        $path = Scalar::string($session->surface['url'] ?? $session->surface['path'] ?? null);

        return '' === $path ? null : \mb_substr($path, 0, self::MAX_PAGE_CHARS);
    }
}
