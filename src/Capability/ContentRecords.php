<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Session\Provenance;
use Odiseo\AiConversationalAgentBundle\Session\SeenRecord;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Site\MediaUrlResolver;

/**
 * How a page enters the session's provenance and comes back out as a card. A card only shows
 * pages a tool returned; title, link and image are the site's, never the model's.
 */
final class ContentRecords
{
    public const KIND = 'content';
    public const RESOLVE_HINT = 'find it with search_content or read it with get_content first';

    public function __construct(private readonly MediaUrlResolver $media)
    {
    }

    public static function remember(TurnState $state, ContentDocument $document, string $type, string $url): void
    {
        $state->remember(new SeenRecord($document->id, self::KIND, [
            'title' => $document->title,
            'type' => $type,
            'url' => $url,
            'summary' => $document->summary,
            'media_id' => $document->mediaId,
            'locale' => $document->locale,
        ]));
    }

    /** The record behind an id the model wrote; a note for the model when the session never saw it. */
    public static function resolve(EnrichmentContext $context, mixed $id): ?SeenRecord
    {
        return PayloadGuard::seen($context, $id, self::KIND, self::RESOLVE_HINT);
    }

    /** Same lookup without a note, for frames of a call still being written. */
    public static function peek(EnrichmentContext $context, mixed $id): ?SeenRecord
    {
        $id = \is_scalar($id) ? \trim((string) $id) : '';

        return '' === $id ? null : Provenance::find($context->tools->state, $id, self::KIND);
    }

    /** @return array<string, mixed> */
    public function card(SeenRecord $record): array
    {
        $data = $record->data;
        $mediaId = \is_numeric($data['media_id'] ?? null) ? (int) $data['media_id'] : null;
        $locale = \is_string($data['locale'] ?? null) ? $data['locale'] : 'en';

        return \array_filter([
            'content_id' => $record->id,
            'title' => \is_string($data['title'] ?? null) ? $data['title'] : '',
            'type' => $data['type'] ?? null,
            'url' => $data['url'] ?? null,
            'summary' => $data['summary'] ?? null,
            'image' => $this->media->thumbnail($mediaId, $locale),
        ], static fn (mixed $v): bool => null !== $v && '' !== $v);
    }
}
