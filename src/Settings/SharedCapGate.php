<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

use Odiseo\AiConversationalAgentBundle\Budget\BudgetPolicy;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Whether the day or month cap every visitor shares is reached on this webspace. Asked on every
 * page that renders the widget, so the answer is kept a minute: a cap reached in between lets a
 * few more visitors see the assistant, and their turns still stop on the cap.
 */
final class SharedCapGate
{
    private const TTL_SECONDS = 60;

    public function __construct(
        private readonly BudgetPolicy $budget,
        private readonly CurrentWebspace $webspace,
        private readonly CacheItemPoolInterface $cache,
        private readonly ?string $timezone = null,
    ) {
    }

    public function isReached(): bool
    {
        $key = \preg_replace('/[^A-Za-z0-9_.]/', '_', $this->webspace->key() ?? 'none');
        $item = $this->cache->getItem('odiseo_site_assistant.shared_cap.' . $key);
        if ($item->isHit()) {
            return true === $item->get();
        }

        $now = new \DateTimeImmutable('now', null === $this->timezone ? null : new \DateTimeZone($this->timezone));
        try {
            $reached = null !== $this->budget->sharedLimit($now);
        } catch (\Throwable) {
            // A ledger that cannot be read (no tables yet) does not hide the widget.
            $reached = false;
        }
        $this->cache->save($item->set($reached)->expiresAfter(self::TTL_SECONDS));

        return $reached;
    }
}
