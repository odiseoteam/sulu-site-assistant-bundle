<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Conversation;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\MessageInterface;

/**
 * A conversation as the admin reads it: the visitor's messages, the assistant's replies and what
 * it looked up or showed on the way, with the pages each card named. Tool results stay out; the
 * model's inputs are enough to follow it.
 *
 * @phpstan-type Page array{content_id: string, title: ?string, note: ?string}
 * @phpstan-type Tool array{name: string, query: ?string, title: ?string, summary: ?string, topic: ?string, pages: list<Page>}
 * @phpstan-type Entry array{role: string, text: ?string, tools: list<Tool>, createdAt: string}
 */
final class Transcript
{
    public const VISIBLE_TOOLS = [
        'search_content',
        'get_content',
        'search_knowledge',
        'present_pages',
        'present_comparison',
        'present_guide',
        'request_handoff',
        'present_handoff',
    ];

    /** Where each tool's input names pages: the rows key (null for a single page) and the note field. */
    private const PAGE_INPUTS = [
        'get_content' => [null, null],
        'present_pages' => ['picks', 'reason'],
        'present_comparison' => ['entries', 'best_for'],
    ];

    /** @var array<string, ?string> */
    private array $titles = [];

    /** @param class-string<MessageInterface> $messageClass */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $messageClass,
        private readonly ?ContentIndexInterface $index = null,
    ) {
    }

    /** @return list<Entry> */
    public function entries(ConversationInterface $conversation): array
    {
        /** @var list<MessageInterface> $messages */
        $messages = $this->em->getRepository($this->messageClass)->findBy(['conversation' => $conversation], ['position' => 'ASC']);

        $entries = [];
        foreach ($messages as $message) {
            $tools = $this->tools($message->getPayload());
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
     * @return list<Tool>
     */
    private function tools(array $payload): array
    {
        $tools = [];
        foreach (Scalar::rows($payload['content'] ?? null) as $block) {
            $name = 'tool_use' === ($block['type'] ?? null) ? ($block['name'] ?? null) : null;
            if (!\is_string($name) || !\in_array($name, self::VISIBLE_TOOLS, true)) {
                continue;
            }
            $input = Scalar::keyed($block['input'] ?? null);
            $tools[] = [
                'name' => $name,
                'query' => Scalar::nullableString($input['query'] ?? null),
                'title' => Scalar::nullableString($input['title'] ?? null),
                'summary' => Scalar::nullableString($input['summary'] ?? null),
                'topic' => Scalar::nullableString($input['topic'] ?? null),
                'pages' => $this->pages($name, $input),
            ];
        }

        return $tools;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<Page>
     */
    private function pages(string $tool, array $input): array
    {
        $rows = [];
        if (isset(self::PAGE_INPUTS[$tool])) {
            [$rowsKey, $noteKey] = self::PAGE_INPUTS[$tool];
            foreach (null === $rowsKey ? [$input] : Scalar::rows($input[$rowsKey] ?? null) as $row) {
                $rows[] = [Scalar::string($row['content_id'] ?? null), null === $noteKey ? null : Scalar::nullableString($row[$noteKey] ?? null)];
            }
        } elseif ('present_guide' === $tool) {
            foreach (Scalar::strings($input['content_ids'] ?? null) as $id) {
                $rows[] = [$id, null];
            }
        }

        $pages = [];
        foreach ($rows as [$id, $note]) {
            $id = \trim($id);
            if ('' !== $id) {
                $pages[] = ['content_id' => $id, 'title' => $this->title($id), 'note' => $note];
            }
        }

        return $pages;
    }

    private function title(string $id): ?string
    {
        if (!\array_key_exists($id, $this->titles)) {
            try {
                $this->titles[$id] = $this->index?->get($id)?->title;
            } catch (\Throwable) {
                $this->titles[$id] = null;
            }
        }

        return $this->titles[$id];
    }
}
