<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Site;

use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

/** The thumbnail a card shows for a media id, or null when it is gone or has no such format. */
final class MediaUrlResolver
{
    public function __construct(
        private readonly MediaManagerInterface $media,
        private readonly string $format,
    ) {
    }

    public function thumbnail(?int $mediaId, string $locale): ?string
    {
        if (null === $mediaId) {
            return null;
        }

        try {
            $thumbnails = $this->media->getById($mediaId, $locale)->getThumbnails();
        } catch (\Throwable) {
            return null;
        }

        $url = $thumbnails[$this->format] ?? null;

        return \is_string($url) ? $url : null;
    }
}
