<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability\Presentation;

/** Schema pieces every card's tool shares. */
final class CardSchema
{
    /** @return array<string, string> */
    public static function contentId(): array
    {
        return ['type' => 'string', 'description' => 'content_id returned by a tool this session.'];
    }

    /** @return array<string, string|int> */
    public static function title(string $what): array
    {
        return ['type' => 'string', 'maxLength' => 80, 'description' => \sprintf('Short heading for the %s.', $what)];
    }

    /**
     * @param mixed $value a list of rows
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        /** @var list<array<string, mixed>> */
        return \is_array($value) ? \array_values(\array_filter($value, 'is_array')) : [];
    }
}
