<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

/** What the assistant can read of the site. The default reads the bundle's SEAL index. */
interface ContentIndexInterface
{
    /**
     * @param list<string> $templateKeys empty means any template
     *
     * @return list<ContentHit>
     */
    public function search(string $query, string $locale, ?string $webspace, array $templateKeys, int $limit): array;

    public function get(string $id): ?ContentDocument;
}
