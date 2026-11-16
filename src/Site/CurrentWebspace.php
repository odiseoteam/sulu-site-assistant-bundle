<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Site;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Session\CurrentSession;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The webspace the assistant works for: the page's on a website request, the one the widget
 * names on the agent's routes, else the one the conversation in progress started on (a queue
 * worker extracting memory, say), else the configured one.
 */
final class CurrentWebspace implements ResetInterface
{
    public const HEADER = 'X-Agent-Webspace';

    /** @var array<string, ?string> */
    private array $bySession = [];

    /** @param class-string<ConversationInterface> $conversationClass */
    public function __construct(
        private readonly Webspaces $webspaces,
        private readonly RequestStack $requests,
        private readonly CurrentSession $session,
        private readonly EntityManagerInterface $em,
        private readonly string $conversationClass,
        private readonly ?RequestAnalyzerInterface $analyzer = null,
    ) {
    }

    public function key(): ?string
    {
        $request = $this->requests->getMainRequest();
        if (null !== $request) {
            $analyzed = $this->analyzed();
            if (null !== $analyzed) {
                return $analyzed;
            }

            $asked = $request->headers->get(self::HEADER);
            if ($this->webspaces->has($asked)) {
                return $asked;
            }
        }

        return $this->fromSession() ?? $this->webspaces->default();
    }

    public function reset(): void
    {
        $this->bySession = [];
    }

    private function analyzed(): ?string
    {
        try {
            $webspace = $this->analyzer?->getWebspace();
        } catch (\Throwable) {
            return null;
        }

        return $webspace instanceof Webspace ? $webspace->getKey() : null;
    }

    private function fromSession(): ?string
    {
        $sessionId = $this->session->sessionId();
        if (null === $sessionId) {
            return null;
        }

        if (!\array_key_exists($sessionId, $this->bySession)) {
            /** @var ConversationInterface|null $conversation */
            $conversation = $this->em->getRepository($this->conversationClass)->findOneBy(['sessionId' => $sessionId]);
            $this->bySession[$sessionId] = $conversation?->getWebspaceKey();
        }

        return $this->bySession[$sessionId];
    }
}
